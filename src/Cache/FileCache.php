<?php

declare(strict_types=1);

namespace TGbotPHP\Cache;

use TGbotPHP\Exceptions\StorageException;
use TGbotPHP\Support\Value;

/**
 * Filesystem cache that persists between requests
 *
 * Values are serialized without allowing objects to be restored, so only
 * scalars and arrays round-trip. Keep the directory outside the web root.
 *
 * put, forget, the removal of an expired entry and prune all take the same
 * per-key lock that update() holds. Otherwise a read could unlink a file
 * that a concurrent update has just replaced.
 */
class FileCache implements CacheInterface
{
    private string $directory;

    /**
     * Lock handles held by this process, with a re-entry count.
     * flock() on a second handle in the same process would deadlock.
     *
     * @var array<string, array{0: resource, 1: int}>
     */
    private array $locks = [];

    public function __construct(string $directory)
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new StorageException("Unable to create cache directory: $directory");
        }

        if (!is_writable($directory)) {
            throw new StorageException("Cache directory is not writable: $directory");
        }

        $this->directory = rtrim($directory, '/\\');
    }

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->read($key);

        return $entry !== null ? $entry['value'] : $default;
    }

    #[\Override]
    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->lock($key);

        try {
            $this->store($key, $value, $ttl);
        } finally {
            $this->unlock($key);
        }
    }

    /**
     * Holds an exclusive lock while the callback runs, so concurrent processes
     * updating the same key wait for each other
     */
    #[\Override]
    public function update(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $this->lock($key);

        try {
            $value = $callback($this->get($key));

            if ($value === null) {
                $this->forget($key);
            } else {
                $this->put($key, $value, $ttl);
            }

            return $value;
        } finally {
            $this->unlock($key);
        }
    }

    #[\Override]
    public function forget(string $key): void
    {
        $this->lock($key);

        try {
            $path = $this->path($key);

            if (is_file($path)) {
                @unlink($path);
            }
        } finally {
            $this->unlock($key);
        }
    }

    #[\Override]
    public function flush(): void
    {
        foreach ([...$this->files('.cache'), ...$this->files('.lock'), ...$this->files('.tmp')] as $file) {
            @unlink($file);
        }
    }

    #[\Override]
    public function has(string $key): bool
    {
        return $this->read($key) !== null;
    }

    /**
     * Remove expired entries
     */
    public function prune(): int
    {
        $removed = 0;

        foreach ($this->files('.cache') as $file) {
            $stripe = self::stripeOf(basename($file));

            if ($stripe === null) {
                continue;
            }

            $this->lockStripe($stripe);

            try {
                if (!is_file($file)) {
                    continue;
                }

                $entry = $this->decode((string) @file_get_contents($file));

                if ($entry === null || self::isExpired($entry)) {
                    @unlink($file);
                    $removed++;
                }
            } finally {
                $this->unlockStripe($stripe);
            }
        }

        return $removed;
    }

    /**
     * @return array{expires: ?int, value: mixed}|null
     */
    private function read(string $key): ?array
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $entry = $this->decode((string) @file_get_contents($path));

        if ($entry !== null && !self::isExpired($entry)) {
            return $entry;
        }

        // Re-read under the lock. The file may have been replaced since the check above.
        $this->lock($key);

        try {
            $fresh = @file_get_contents($path);

            if (!is_string($fresh)) {
                return null;
            }

            $entry = $this->decode($fresh);

            if ($entry === null || self::isExpired($entry)) {
                @unlink($path);

                return null;
            }

            return $entry;
        } finally {
            $this->unlock($key);
        }
    }

    private function store(string $key, mixed $value, ?int $ttl): void
    {
        $payload = serialize([
            'expires' => $ttl !== null ? time() + $ttl : null,
            'value' => $value,
        ]);

        $path = $this->path($key);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        // Readable by the bot only, whatever the umask and the directory permissions
        if (file_put_contents($tmp, $payload, LOCK_EX) === false || !chmod($tmp, 0600) || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new StorageException("Unable to write cache entry: $key");
        }
    }

    /**
     * @return array{expires: ?int, value: mixed}|null
     */
    private function decode(string $contents): ?array
    {
        if ($contents === '') {
            return null;
        }

        $entry = @unserialize($contents, ['allowed_classes' => false]);

        if (!is_array($entry) || !array_key_exists('value', $entry)) {
            return null;
        }

        return [
            'expires' => Value::nullableInt($entry['expires'] ?? null),
            'value' => $entry['value'],
        ];
    }

    /**
     * @param array{expires: ?int, value: mixed} $entry
     */
    private static function isExpired(array $entry): bool
    {
        return $entry['expires'] !== null && time() >= $entry['expires'];
    }

    /**
     * @return list<string>
     */
    private function files(string $suffix): array
    {
        $entries = scandir($this->directory);

        if ($entries === false) {
            return [];
        }

        $files = [];

        foreach ($entries as $name) {
            if ($name !== '.' && $name !== '..' && str_ends_with($name, $suffix)) {
                $files[] = $this->directory . DIRECTORY_SEPARATOR . $name;
            }
        }

        return $files;
    }

    /**
     * Lock the stripe a key belongs to: 256 lock files at most, whatever the number of keys
     */
    private function lock(string $key): void
    {
        $this->lockStripe(substr(hash('sha256', $key), 0, 2));
    }

    private function unlock(string $key): void
    {
        $this->unlockStripe(substr(hash('sha256', $key), 0, 2));
    }

    private function lockStripe(string $stripe): void
    {
        if (isset($this->locks[$stripe])) {
            $this->locks[$stripe][1]++;

            return;
        }

        $path = $this->directory . '/' . $stripe . '.lock';
        $handle = @fopen($path, 'c');

        if ($handle === false) {
            throw new StorageException("Unable to open cache lock: $path");
        }

        @chmod($path, 0600);

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new StorageException("Unable to lock cache stripe: $stripe");
        }

        $this->locks[$stripe] = [$handle, 1];
    }

    private function unlockStripe(string $stripe): void
    {
        if (!isset($this->locks[$stripe])) {
            return;
        }

        $this->locks[$stripe][1]--;

        if ($this->locks[$stripe][1] > 0) {
            return;
        }

        $handle = $this->locks[$stripe][0];
        unset($this->locks[$stripe]);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private static function stripeOf(string $basename): ?string
    {
        $hash = substr($basename, 0, -strlen('.cache'));

        return strlen($hash) >= 2 ? substr($hash, 0, 2) : null;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.cache';
    }
}
