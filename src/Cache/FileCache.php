<?php

declare(strict_types=1);

namespace TGbotPHP\Cache;

use JsonException;
use TGbotPHP\Exceptions\StorageException;
use TGbotPHP\Support\GuardedCall;
use TGbotPHP\Support\Platform\PlatformGuard;
use TGbotPHP\Support\SafeFileRemover;
use TGbotPHP\Support\Value;

/**
 * Filesystem cache that persists between requests.
 *
 * Entries are stored as JSON with an explicit envelope: no PHP object is ever
 * reconstructed from the file, so a tampered entry cannot execute code.
 * Keep the directory outside the web root.
 *
 * put(), forget(), the removal of an expired entry and prune() all take the
 * same per-key lock that update() holds. Otherwise a read could unlink a file
 * that a concurrent update has just replaced.
 */
class FileCache implements CacheInterface
{
    /** Suffix of the files holding JSON entries. */
    private const string ENTRY_SUFFIX = '.cache';

    /** Version of the on-disk JSON envelope. */
    private const int FORMAT = 1;

    /** Absolute path of the cache directory. */
    private string $directory;

    /**
     * Lock handles held by this process, with a re-entry count.
     * flock() on a second handle in the same process would deadlock.
     *
     * @var array<string, array{0: resource, 1: int}>
     */
    private array $locks = [];

    /**
     * @param string $directory Directory to create and use for cache files
     *
     * @throws StorageException When the directory cannot be created or written
     */
    public function __construct(string $directory)
    {
        if (
            !is_dir($directory)
            && GuardedCall::run(static fn(): bool => mkdir($directory, 0700, true)) !== true
            && !is_dir($directory)
        ) {
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
     * Hold an exclusive lock while the callback runs, so concurrent processes
     * updating the same key wait for each other.
     */
    #[\Override]
    public function update(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $this->lock($key);

        try {
            $value = $callback($this->get($key));

            if ($value === null) {
                $this->forget($key);

                return null;
            }

            $this->put($key, $value, $ttl);

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
            SafeFileRemover::remove([$this->path($key)], $this->directory);
        } finally {
            $this->unlock($key);
        }
    }

    #[\Override]
    public function flush(): void
    {
        SafeFileRemover::remove(
            [...$this->files(self::ENTRY_SUFFIX), ...$this->files('.lock'), ...$this->files('.tmp')],
            $this->directory,
        );
    }

    #[\Override]
    public function has(string $key): bool
    {
        return $this->read($key) !== null;
    }

    /**
     * Remove expired entries and entries that cannot be decoded.
     *
     * @return int Number of removed entries
     */
    public function prune(): int
    {
        $removed = 0;

        foreach ($this->files(self::ENTRY_SUFFIX) as $file) {
            $stripe = self::stripeOf(basename($file));

            if ($stripe === null) {
                continue;
            }

            $this->lockStripe($stripe);

            try {
                if (!is_file($file)) {
                    continue;
                }

                $contents = GuardedCall::run(static fn(): string|false => file_get_contents($file));
                $entry = $this->decode(is_string($contents) ? $contents : '');

                if ($entry === null || self::isExpired($entry)) {
                    SafeFileRemover::remove([$file], $this->directory);
                    $removed++;
                }
            } finally {
                $this->unlockStripe($stripe);
            }
        }

        return $removed;
    }

    /**
     * @return array{expires: ?int, value: mixed}|null Decoded entry or null when missing/expired
     */
    private function read(string $key): ?array
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $contents = GuardedCall::run(static fn(): string|false => file_get_contents($path));
        $entry = $this->decode(is_string($contents) ? $contents : '');

        if ($entry !== null && !self::isExpired($entry)) {
            return $entry;
        }

        return $this->readLocked($key, $path);
    }

    /**
     * Re-read under the lock: the file may have been replaced since the unlocked check.
     *
     * @return array{expires: ?int, value: mixed}|null
     */
    private function readLocked(string $key, string $path): ?array
    {
        $this->lock($key);

        try {
            $fresh = GuardedCall::run(static fn(): string|false => file_get_contents($path));

            if (!is_string($fresh)) {
                return null;
            }

            $entry = $this->decode($fresh);

            if ($entry !== null && !self::isExpired($entry)) {
                return $entry;
            }

            SafeFileRemover::remove([$path], $this->directory);

            return null;
        } finally {
            $this->unlock($key);
        }
    }

    /**
     * Write the entry through a temporary file and an atomic rename.
     *
     * @throws StorageException When the entry cannot be written
     */
    private function store(string $key, mixed $value, ?int $ttl): void
    {
        try {
            $payload = json_encode([
                'v' => self::FORMAT,
                'expires' => $ttl !== null ? time() + $ttl : null,
                'value' => $value,
            ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (JsonException $e) {
            throw new StorageException("Unable to encode cache entry: $key", 0, $e);
        }

        $path = $this->path($key);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        // Owner-only on every platform (POSIX 0600 / Windows NTFS ACL)
        if (
            GuardedCall::run(static fn(): int|false => file_put_contents($tmp, $payload, LOCK_EX)) === false
            || !PlatformGuard::current()->restrict($tmp)
            || GuardedCall::run(static fn(): bool => rename($tmp, $path)) !== true
        ) {
            SafeFileRemover::remove([$tmp], $this->directory);
            throw new StorageException("Unable to write cache entry: $key");
        }
    }

    /**
     * Decode a JSON envelope with an explicit schema.
     *
     * Only the known keys are read and no PHP type is reconstructed from the
     * file, so a tampered entry can never instantiate an object.
     *
     * @return array{expires: ?int, value: mixed}|null
     */
    private function decode(string $contents): ?array
    {
        if ($contents === '') {
            return null;
        }

        try {
            $entry = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $entry = null;
        }

        if (
            !is_array($entry)
            || ($entry['v'] ?? null) !== self::FORMAT
            || !array_key_exists('expires', $entry)
            || !array_key_exists('value', $entry)
        ) {
            return null;
        }

        return [
            'expires' => Value::nullableInt($entry['expires']),
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
     * @return list<string> Absolute paths of files ending with the given suffix
     */
    private function files(string $suffix): array
    {
        $directory = $this->directory;
        $entries = GuardedCall::run(static fn(): array|false => scandir($directory));

        if (!is_array($entries)) {
            return [];
        }

        $files = [];

        foreach ($entries as $name) {
            if (!is_string($name) || $name === '.' || $name === '..' || !str_ends_with($name, $suffix)) {
                continue;
            }

            $files[] = $this->directory . DIRECTORY_SEPARATOR . $name;
        }

        return $files;
    }

    /** Lock the stripe a key belongs to: 256 lock files at most, whatever the number of keys. */
    private function lock(string $key): void
    {
        $this->lockStripe(substr(hash('sha256', $key), 0, 2));
    }

    private function unlock(string $key): void
    {
        $this->unlockStripe(substr(hash('sha256', $key), 0, 2));
    }

    /**
     * @throws StorageException When the lock file cannot be opened or locked
     */
    private function lockStripe(string $stripe): void
    {
        if (isset($this->locks[$stripe])) {
            $this->locks[$stripe][1]++;

            return;
        }

        $path = $this->directory . '/' . $stripe . '.lock';
        $handle = GuardedCall::run(static fn() => fopen($path, 'c'));

        if (!is_resource($handle)) {
            throw new StorageException("Unable to open cache lock: $path");
        }

        PlatformGuard::current()->restrict($path);

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

    /** Extract the stripe from a cache file name, or null for unrelated files. */
    private static function stripeOf(string $basename): ?string
    {
        $hash = substr($basename, 0, -strlen(self::ENTRY_SUFFIX));

        return strlen($hash) >= 2 ? substr($hash, 0, 2) : null;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . self::ENTRY_SUFFIX;
    }
}
