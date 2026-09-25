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
 */
class FileCache implements CacheInterface
{
    private string $directory;

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
     * Holds an exclusive lock while the callback runs, so concurrent processes
     * updating the same key wait for each other
     */
    #[\Override]
    public function update(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $lock = $this->lock($key);

        try {
            $value = $callback($this->get($key));

            if ($value === null) {
                $this->forget($key);
            } else {
                $this->put($key, $value, $ttl);
            }

            return $value;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    #[\Override]
    public function forget(string $key): void
    {
        $path = $this->path($key);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    #[\Override]
    public function flush(): void
    {
        foreach ($this->files() as $file) {
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

        foreach ($this->files() as $file) {
            $entry = $this->decode((string) @file_get_contents($file));
            if ($entry === null || self::isExpired($entry)) {
                @unlink($file);
                $removed++;
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

        if ($entry === null || self::isExpired($entry)) {
            @unlink($path);
            return null;
        }

        return $entry;
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
    private function files(): array
    {
        $files = glob($this->directory . '/*.cache');

        return $files === false ? [] : $files;
    }

    /**
     * Lock the stripe a key belongs to: 256 lock files at most, whatever the number of keys
     *
     * @return resource
     */
    private function lock(string $key)
    {
        $path = $this->directory . '/' . substr(hash('sha256', $key), 0, 2) . '.lock';
        $handle = @fopen($path, 'c');

        if ($handle === false) {
            throw new StorageException("Unable to open cache lock: $path");
        }

        @chmod($path, 0600);

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new StorageException("Unable to lock cache entry: $key");
        }

        return $handle;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.cache';
    }
}
