<?php

declare(strict_types=1);

namespace TGbotPHP\Support;

/**
 * Removes only files that resolve inside an explicitly authorized directory.
 *
 * Callers hand over candidate paths (which may be derived from keys or from a
 * directory listing); this class canonicalizes both the root and each target
 * before the deletion, so a path that escapes the root is silently ignored.
 */
final class SafeFileRemover
{
    /**
     * @param list<string> $paths Absolute or relative candidate paths
     * @param string $root Directory the deleted files must stay inside
     */
    public static function remove(array $paths, string $root): void
    {
        $rootReal = realpath($root);

        if ($rootReal === false) {
            return;
        }

        $prefix = rtrim($rootReal, '/\\') . DIRECTORY_SEPARATOR;
        $safe = [];

        foreach ($paths as $path) {
            $name = basename($path);

            if ($name === '' || $name === '.' || $name === '..' || preg_match('/[\\\\\/]/', $name) === 1) {
                continue;
            }

            $real = realpath($path);

            if ($real === false || !str_starts_with($real, $prefix) || is_dir($real)) {
                continue;
            }

            // Delete the entry itself, not the target of a possible symlink.
            $safe[] = $prefix . $name;
        }

        if ($safe !== []) {
            // The callback form keeps the deletion in one audited place and
            // receives only paths canonicalized above.
            array_map('unlink', $safe);
        }
    }
}
