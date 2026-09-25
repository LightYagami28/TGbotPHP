<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TGbotPHP\Cache\ArrayCache;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Cache\FileCache;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Exceptions\StorageException;
use TGbotPHP\Rate\RateLimiter;
use TGbotPHP\Session\ConversationManager;
use TGbotPHP\Session\SessionManager;
use TGbotPHP\Support\Value;
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
            $cache = new FileCache($file . '/cache');
            self::fail('Created a cache under a file: ' . $cache::class);
        } finally {
            unlink($file);
        }
    }

    public function testFileCacheFilesAreOnlyReadableByTheOwner(): void
    {
        $directory = self::$directory . '-perm';
        @mkdir($directory, 0755);
        chmod($directory, 0755);
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

        $sessions->setSessionData('missing', 'lang', 'it');
        self::assertNull($sessions->getSession('missing'));

        $sessions->endSession($id);
        self::assertNull($sessions->getSession($id));
    }
}
