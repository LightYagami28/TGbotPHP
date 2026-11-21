<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Support;

/**
 * Starts the external processes used by the test suite.
 *
 * Every command is a fixed string literal. Values that differ between runs
 * travel through the environment or through the working directory, so no
 * command is ever assembled from runtime data.
 */
final class TestProcesses
{
    /**
     * Starts PHP's built-in server on an ephemeral local port.
     *
     * The port is chosen by the server itself; the caller reads it from the
     * process output. Nothing from the environment is interpolated.
     *
     * @return array{0: resource, 1: array<int, resource>}
     */
    public static function startServer(string $workingDirectory): array
    {
        $pipes = [];
        $process = proc_open(
            'php -S 127.0.0.1:0 tests/Support/Server/router.php',
            [1 => ['file', self::nullDevice(), 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workingDirectory,
            null,
            ['bypass_shell' => true],
        );

        if ($process === false) {
            throw new \RuntimeException('Unable to start the test server');
        }

        return [$process, $pipes];
    }

    /**
     * Runs the cache worker used to check cross-process updates.
     *
     * @return resource
     */
    public static function startCacheWorker(string $workingDirectory, string $cacheDirectory)
    {
        $environment = getenv();
        $environment['TGBOTP_TEST_CACHE_DIR'] = $cacheDirectory;

        $pipes = [];
        $process = proc_open(
            'php tests/Support/Cache/cache-worker.php',
            [1 => ['file', self::nullDevice(), 'w'], 2 => ['file', self::nullDevice(), 'w']],
            $pipes,
            $workingDirectory,
            $environment,
            ['bypass_shell' => true],
        );

        if ($process === false) {
            throw new \RuntimeException('Unable to start the cache worker');
        }

        return $process;
    }

    /**
     * Runs a worker that holds a cache lock and mutates an entry.
     *
     * @param array<string, string> $environment
     * @return resource
     */
    public static function startLockWorker(string $workingDirectory, array $environment)
    {
        $pipes = [];
        $process = proc_open(
            'php tests/Support/Cache/lock-worker.php',
            [1 => ['file', self::nullDevice(), 'w'], 2 => ['file', self::nullDevice(), 'w']],
            $pipes,
            $workingDirectory,
            array_merge(getenv(), $environment),
            ['bypass_shell' => true],
        );

        if ($process === false) {
            throw new \RuntimeException('Unable to start the lock worker');
        }

        return $process;
    }

    private static function nullDevice(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    }
}
