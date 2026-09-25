<?php

declare(strict_types=1);

namespace TGbotPHP\Cache;

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
            throw new \RuntimeException("Unable to create cache directory: $directory");
        }

        if (!is_writable($directory)) {
            throw new \RuntimeException("Cache directory is not writable: $directory");
        }

        $this->directory = rtrim($directory, '/\\');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->read($key);

        return $entry !== null ? $entry['value'] : $default;
    }

    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        $payload = serialize([
            'expires' => $ttl !== null ? time() + $ttl : null,
            'value' => $value,
        ]);

        $path = $this->path($key);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($tmp, $payload, LOCK_EX) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("Unable to write cache entry: $key");
        }
    }

    public function forget(string $key): void
    {
        $path = $this->path($key);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function flush(): void
    {
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

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

        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
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
            'expires' => isset($entry['expires']) ? (int) $entry['expires'] : null,
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

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.cache';
    }
}
