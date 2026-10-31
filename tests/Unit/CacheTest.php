<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TGbotPHP\Cache\ArrayCache;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Cache\FileCache;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Exceptions\StorageException;
use TGbotPHP\Rate\RateLimiter;
use TGbotPHP\Session\ConversationManager;
use TGbotPHP\Session\SessionManager;
use TGbotPHP\Support\Value;
use TGbotPHP\Tests\Support\RuntimeControls;
use TGbotPHP\Tests\Support\Updates;

final class CacheTest extends TestCase
{
    private static string $directory;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        self::$directory = sys_get_temp_dir() . '/tgbotphp-test-' . bin2hex(random_bytes(4));
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        $files = glob(self::$directory . '/*');

        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }
        @rmdir(self::$directory);
    }

    /**
     * @return array<string, array{0: callable(): CacheInterface}>
     */
    public static function caches(): array
    {
        return [
            'array' => [static fn(): CacheInterface => new ArrayCache()],
            'file' => [static function (): CacheInterface {
                $cache = new FileCache(self::$directory);
                $cache->flush();
                return $cache;
            }],
        ];
    }

    /**
     * @param callable(): CacheInterface $factory
     */
    #[DataProvider('caches')]
    public function testStoresValues(callable $factory): void
    {
        $cache = $factory();

        self::assertFalse($cache->has('a'));
        self::assertSame('default', $cache->get('a', 'default'));

        $cache->put('a', ['nested' => [1, 2]]);
        $cache->put('null', null);

        self::assertTrue($cache->has('a'));
        self::assertTrue($cache->has('null'));
        self::assertSame(['nested' => [1, 2]], $cache->get('a'));

        $cache->forget('a');
        self::assertFalse($cache->has('a'));

        $cache->flush();
        self::assertFalse($cache->has('null'));
    }

    /**
     * @param callable(): CacheInterface $factory
     */
    #[DataProvider('caches')]
    public function testExpiresValues(callable $factory): void
    {
        $cache = $factory();

        $cache->put('expired', 1, 0);
        $cache->put('fresh', 1, 60);

        self::assertFalse($cache->has('expired'));
        self::assertTrue($cache->has('fresh'));
    }

    /**
     * @param callable(): CacheInterface $factory
     */
    #[DataProvider('caches')]
    public function testUpdatesValues(callable $factory): void
    {
        $cache = $factory();

        self::assertSame(1, $cache->update('counter', static fn(mixed $value): int => Value::int($value) + 1));
        self::assertSame(2, $cache->update('counter', static fn(mixed $value): int => Value::int($value) + 1, 60));
        self::assertSame(2, $cache->get('counter'));

        self::assertNull($cache->update('counter', static fn(): mixed => null));
        self::assertFalse($cache->has('counter'));
    }

    public function testFileCacheUpdatesAreAtomicAcrossProcesses(): void
    {
        $cache = new FileCache(self::$directory);
        $cache->forget('shared-counter');

        $script = sprintf(
            'require %s; $c = new TGbotPHP\Cache\FileCache(%s); for ($i = 0; $i < 50; $i++) { $c->update("shared-counter", fn($v) => (int) $v + 1); }',
            var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true),
            var_export(self::$directory, true),
        );

        $processes = [];
        for ($i = 0; $i < 4; $i++) {
            $process = proc_open([PHP_BINARY, '-r', $script], [], $pipes);
            self::assertIsResource($process);
            $processes[] = $process;
        }

        foreach ($processes as $process) {
            self::assertSame(0, proc_close($process));
        }

        self::assertSame(200, $cache->get('shared-counter'));
    }

    public function testFileCachePersistsAcrossInstances(): void
    {
        (new FileCache(self::$directory))->put('shared', 'value', 60);

        self::assertSame('value', (new FileCache(self::$directory))->get('shared'));
    }

    public function testFileCachePrunesExpiredAndCorruptEntries(): void
    {
        $cache = new FileCache(self::$directory);
        $cache->flush();

        $cache->put('expired', 1, 0);
        $cache->put('fresh', 1, 60);
        $cache->put('corrupt', 1);
        file_put_contents(self::$directory . '/' . hash('sha256', 'corrupt') . '.cache', 'not serialized');

        self::assertSame(2, $cache->prune());
        self::assertTrue($cache->has('fresh'));
        self::assertNull($cache->get('corrupt'));
    }

    public function testFileCacheIgnoresUnexpectedContents(): void
    {
        $cache = new FileCache(self::$directory);
        file_put_contents(self::$directory . '/' . hash('sha256', 'scalar') . '.cache', serialize('no envelope'));
        file_put_contents(self::$directory . '/' . hash('sha256', 'empty') . '.cache', '');

        self::assertFalse($cache->has('scalar'));
        self::assertFalse($cache->has('empty'));
        $cache->forget('missing');
    }

    public function testFileCacheRejectsUnusableDirectories(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tgbotphp');
        self::assertIsString($file);

        try {
            $this->expectException(StorageException::class);
            $this->expectExceptionMessage('Unable to create cache directory');
            $cache = new FileCache($file . '/cache');
            self::fail('Created a cache under a file: ' . $cache::class);
        } finally {
            unlink($file);
        }
    }

    public function testFileCacheFilesAreOnlyReadableByTheOwner(): void
    {
        // A directory created by someone else, with the default permissions
        $directory = self::$directory . '-perm';
        @mkdir($directory);
        $cache = new FileCache($directory);

        $cache->put('secret', 'value');
        $cache->update('secret', static fn(mixed $value): mixed => $value);

        $files = glob("$directory/*");
        self::assertIsArray($files);
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            self::assertSame('0600', substr(sprintf('%o', fileperms($file)), -4), basename($file));
            unlink($file);
        }
        rmdir($directory);
    }

    public function testFileCacheDoesNotRestoreObjects(): void
    {
        $cache = new FileCache(self::$directory);
        $cache->put('object', new \ArrayObject([1]));

        self::assertInstanceOf(\__PHP_Incomplete_Class::class, $cache->get('object'));
    }

    public function testRateLimiterUsesFixedWindow(): void
    {
        $limiter = new RateLimiter(new ArrayCache());

        self::assertTrue($limiter->limit('k', 2, 60));
        self::assertTrue($limiter->limit('k', 2, 60));
        self::assertFalse($limiter->limit('k', 2, 60));
        self::assertSame(0, $limiter->remaining('k', 2));
        self::assertGreaterThan(0, $limiter->availableIn('k'));

        self::assertSame(0, $limiter->availableIn('unused'));
        self::assertSame(2, $limiter->remaining('unused', 2));

        $limiter->reset('k');
        self::assertSame(2, $limiter->remaining('k', 2));
    }

    public function testRateLimiterMiddleware(): void
    {
        $limited = [];
        $middleware = (new RateLimiter(new ArrayCache()))->middleware(1, 60, function (\stdClass $update) use (&$limited): void {
            $limited[] = Value::int(Value::path($update, 'update_id'));
        });

        $first = UpdateParser::fromArray(Updates::message('a'));
        $second = UpdateParser::fromArray(Updates::message('b'));
        $otherUser = UpdateParser::fromArray(Updates::message('c', userId: 99));

        self::assertTrue($middleware($first));
        self::assertFalse($middleware($second));
        self::assertTrue($middleware($otherUser));
        self::assertSame([$second->update_id], $limited);
    }

    public function testConversationManager(): void
    {
        $manager = new ConversationManager(new ArrayCache());

        self::assertNull($manager->getState(1, 2));

        $manager->setState(1, 2, 'step1', ['a' => 1]);
        $manager->updateData(1, 2, ['b' => 2]);

        self::assertSame('step1', $manager->getState(1, 2));
        self::assertSame(['a' => 1, 'b' => 2], $manager->getData(1, 2));
        self::assertNull($manager->getState(1, 3));

        $manager->updateData(5, 5, ['ignored' => true]);
        self::assertSame([], $manager->getData(5, 5));

        $manager->clear(1, 2);
        self::assertNull($manager->getState(1, 2));
        self::assertSame([], $manager->getData(1, 2));
    }

    public function testSessionManager(): void
    {
        $sessions = new SessionManager(new ArrayCache());

        $id = $sessions->startSession(7);
        $sessions->setSessionData($id, 'lang', 'it');

        self::assertSame(7, $sessions->getSession($id)['user_id'] ?? null);
        self::assertSame('it', $sessions->getSessionData($id, 'lang'));

        self::assertSame('it', $sessions->getSessionData($id, 'lang', default: 'en'));
        self::assertSame('en', $sessions->getSessionData($id, 'missing', default: 'en'));

        $sessions->setSessionData('missing', 'lang', 'it');
        self::assertNull($sessions->getSession('missing'));

        $sessions->endSession($id);
        self::assertNull($sessions->getSession($id));
    }

    public function testFlushRemovesCacheAndLockFiles(): void
    {
        $cache = new FileCache(self::$directory);
        $cache->put('flush-me', 'v');
        $cache->update('flush-me', static fn(mixed $value): mixed => $value);
        $cache->flush();

        $entries = scandir(self::$directory);
        self::assertSame([], array_values(array_diff($entries === false ? [] : $entries, ['.', '..'])));
    }

    public function testFileCacheWorksWhenTheDirectoryNameContainsGlobMetacharacters(): void
    {
        $directory = sys_get_temp_dir() . '/tgbotphp-[cache]-' . bin2hex(random_bytes(3));
        mkdir($directory);

        try {
            $cache = new FileCache($directory);
            $cache->put('fresh', 'yes', 60);
            $cache->put('expired', 'no', 0);

            self::assertSame(1, $cache->prune());
            self::assertSame('yes', $cache->get('fresh'));

            $cache->update('fresh', static fn(mixed $value): mixed => $value);
            $cache->flush();
            $entries = scandir($directory);
            self::assertSame([], array_values(array_diff($entries === false ? [] : $entries, ['.', '..'])));
        } finally {
            $left = scandir($directory);
            if (is_array($left)) {
                foreach ($left as $name) {
                    if ($name !== '.' && $name !== '..') {
                        unlink($directory . '/' . $name);
                    }
                }
            }
            rmdir($directory);
        }
    }

    public function testExpiredReadDoesNotDeleteAValueReplacedUnderTheLock(): void
    {
        $cache = new FileCache(self::$directory);
        $cache->forget('race');
        $cache->put('race', 'old', 0);

        $ready = self::$directory . '/race-ready';
        @unlink($ready);

        // Hold the same stripe lock FileCache uses, then replace the expired
        // entry. get() must wait and re-read instead of unlinking the new value.
        $script = sprintf(
            '$dir = %s; $ready = %s; $hash = hash("sha256", "race");'
            . ' $lock = fopen($dir . "/" . substr($hash, 0, 2) . ".lock", "c");'
            . ' if ($lock === false || !flock($lock, LOCK_EX)) { exit(1); }'
            . ' file_put_contents($ready, "1"); sleep(1);'
            . ' file_put_contents($dir . "/$hash.cache", serialize(["expires" => time() + 60, "value" => "new"]));'
            . ' flock($lock, LOCK_UN); fclose($lock);',
            var_export(self::$directory, true),
            var_export($ready, true),
        );
        $process = proc_open([PHP_BINARY, '-r', $script], [], $pipes);
        self::assertIsResource($process);

        $deadline = time() + 5;
        while (!is_file($ready) && time() < $deadline) {
            usleep(20_000);
        }
        self::assertFileExists($ready);

        $seen = $cache->get('race');
        self::assertSame(0, proc_close($process));
        self::assertSame('new', $seen);
        @unlink($ready);
    }

    public function testRateLimiterRejectsNonPositiveWindows(): void
    {
        $limiter = new RateLimiter(new ArrayCache());

        foreach ([[0, 1], [1, 0], [-1, 5]] as [$maxRequests, $windowSeconds]) {
            try {
                $limiter->limit('k', $maxRequests, $windowSeconds);
                self::fail("Accepted limit $maxRequests / $windowSeconds");
            } catch (\InvalidArgumentException) {
                // expected
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $limiter->middleware(1, 0);
    }

    public function testFileCacheRejectsADirectoryThatIsNotWritable(): void
    {
        $directory = sys_get_temp_dir() . '/tgbotphp-ro-' . bin2hex(random_bytes(3));
        mkdir($directory, 0700);
        chmod($directory, 0555);

        try {
            $this->expectException(StorageException::class);
            $this->expectExceptionMessage('not writable');
            new FileCache($directory);
        } finally {
            chmod($directory, 0700);
            rmdir($directory);
        }
    }

    public function testPruneIgnoresCacheFilesWhoseNameIsNotAHash(): void
    {
        $cache = new FileCache(self::$directory);
        file_put_contents(self::$directory . '/x.cache', 'short');

        self::assertSame(0, $cache->prune());
        self::assertFileExists(self::$directory . '/x.cache');
        unlink(self::$directory . '/x.cache');
    }

    public function testFlushOnAMissingDirectoryListsNothing(): void
    {
        $directory = sys_get_temp_dir() . '/tgbotphp-gone-' . bin2hex(random_bytes(3));
        $cache = new FileCache($directory);
        $moved = $directory . '-moved';
        rename($directory, $moved);
        touch($directory);

        try {
            $cache->flush();
            self::assertFileExists($directory);
        } finally {
            unlink($directory);
            $left = scandir($moved);
            if (is_array($left)) {
                foreach ($left as $name) {
                    if ($name !== '.' && $name !== '..') {
                        unlink($moved . '/' . $name);
                    }
                }
            }
            rmdir($moved);
        }
    }

    public function testPutReportsADirectoryThatCannotCreateFiles(): void
    {
        $cache = new FileCache(self::$directory);
        $cache->put('locked-stripe', 'v');
        $stripe = substr(hash('sha256', 'locked-stripe'), 0, 2);
        $lock = self::$directory . '/' . $stripe . '.lock';
        chmod($lock, 0666);
        chmod(self::$directory, 0555);

        try {
            $cache->put('locked-stripe', 'next');
            self::fail('Wrote a cache entry into a read-only directory');
        } catch (StorageException $e) {
            self::assertStringContainsString('Unable to write cache entry', $e->getMessage());
        } finally {
            chmod(self::$directory, 0700);
        }
    }

    public function testPruneSkipsAFileRemovedWhileWaitingForTheLock(): void
    {
        $directory = sys_get_temp_dir() . '/tgbotphp-prune-' . bin2hex(random_bytes(3));
        $cache = new FileCache($directory);
        $cache->put('prune-race', 'v', 60);
        $hash = hash('sha256', 'prune-race');
        $file = $directory . '/' . $hash . '.cache';
        $ready = $directory . '/prune-ready';
        @unlink($ready);

        $script = sprintf(
            '$lock = fopen(%s, "c"); if ($lock === false || !flock($lock, LOCK_EX)) { exit(1); }'
            . ' file_put_contents(%s, "1"); sleep(1); unlink(%s); flock($lock, LOCK_UN); fclose($lock);',
            var_export($directory . '/' . substr($hash, 0, 2) . '.lock', true),
            var_export($ready, true),
            var_export($file, true),
        );
        $process = proc_open([PHP_BINARY, '-r', $script], [], $pipes);
        self::assertIsResource($process);
        $deadline = time() + 5;
        while (!is_file($ready) && time() < $deadline) {
            usleep(20_000);
        }
        self::assertFileExists($ready);

        try {
            self::assertSame(0, $cache->prune());
            self::assertSame(0, proc_close($process));
        } finally {
            @unlink($ready);
            $cache->flush();
            @rmdir($directory);
        }
    }

    public function testReadOfADeletedExpiredEntryReturnsNull(): void
    {
        $directory = sys_get_temp_dir() . '/tgbotphp-expire-' . bin2hex(random_bytes(3));
        $cache = new FileCache($directory);
        $cache->put('expire-race', 'old', 0);
        $hash = hash('sha256', 'expire-race');
        $file = $directory . '/' . $hash . '.cache';
        $ready = $directory . '/expire-ready';
        @unlink($ready);

        $script = sprintf(
            '$lock = fopen(%s, "c"); if ($lock === false || !flock($lock, LOCK_EX)) { exit(1); }'
            . ' file_put_contents(%s, "1"); sleep(1); unlink(%s); flock($lock, LOCK_UN); fclose($lock);',
            var_export($directory . '/' . substr($hash, 0, 2) . '.lock', true),
            var_export($ready, true),
            var_export($file, true),
        );
        $process = proc_open([PHP_BINARY, '-r', $script], [], $pipes);
        self::assertIsResource($process);
        $deadline = time() + 5;
        while (!is_file($ready) && time() < $deadline) {
            usleep(20_000);
        }
        self::assertFileExists($ready);

        try {
            self::assertNull($cache->get('expire-race'));
            self::assertSame(0, proc_close($process));
        } finally {
            @unlink($ready);
            $cache->flush();
            @rmdir($directory);
        }
    }

    public function testUnlockOfAStripeThatIsNotHeldDoesNothing(): void
    {
        $cache = new FileCache(self::$directory);
        $unlock = new ReflectionMethod(FileCache::class, 'unlock');
        $unlock->invoke($cache, 'not-held');

        self::assertFalse($cache->has('not-held'));
    }

    public function testOpeningALockInAReadOnlyDirectoryIsReported(): void
    {
        $directory = sys_get_temp_dir() . '/tgbotphp-lockro-' . bin2hex(random_bytes(3));
        $cache = new FileCache($directory);
        chmod($directory, 0555);

        try {
            $cache->put('new-key', 'v');
            self::fail('Opened a lock file in a read-only directory');
        } catch (StorageException $e) {
            self::assertStringContainsString('Unable to open cache lock', $e->getMessage());
        } finally {
            chmod($directory, 0700);
            $cache->flush();
            rmdir($directory);
        }
    }

    public function testLockFailureIsReported(): void
    {
        $cache = new FileCache(self::$directory);
        RuntimeControls::$failFlock = true;

        try {
            $cache->put('no-lock', 'v');
            self::fail('Locked a stripe whose flock() failed');
        } catch (StorageException $e) {
            self::assertStringContainsString('Unable to lock cache stripe', $e->getMessage());
        } finally {
            RuntimeControls::reset();
        }
    }
}
