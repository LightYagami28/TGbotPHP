<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TGbotPHP\Exceptions\PluginException;
use TGbotPHP\Plugin\PluginInterface;
use TGbotPHP\Plugin\PluginManager;
use TGbotPHP\Support\Value;

final class PluginManagerTest extends TestCase
{
    public function testRegistersActivatesAndDeactivatesPlugins(): void
    {
        $manager = new PluginManager();
        $plugin = self::plugin();

        $manager->register('stats', $plugin);

        self::assertTrue($manager->isActive('stats'));
        self::assertSame($plugin, $manager->get('stats'));
        self::assertSame(['stats' => $plugin], $manager->all());
        self::assertSame(['activate'], $plugin->calls);

        $manager->unregister('stats');
        $manager->unregister('stats');

        self::assertFalse($manager->isActive('stats'));
        self::assertNull($manager->get('stats'));
        self::assertSame(['activate', 'deactivate'], $plugin->calls);
    }

    public function testRejectsDuplicateNames(): void
    {
        $manager = new PluginManager();
        $manager->register('stats', self::plugin());

        $this->expectException(PluginException::class);

        $manager->register('stats', self::plugin());
    }

    public function testHooksRunByPriorityAndPassTheValueAlong(): void
    {
        $manager = new PluginManager();
        $manager->addHook('text', static fn(mixed $v): string => Value::string($v) . 'b', 20);
        $manager->addHook('text', static fn(mixed $v): string => Value::string($v) . 'a', 5);
        $manager->addHook('text', static fn(mixed $v): string => Value::string($v) . 'c', 20);

        self::assertSame('>abc', $manager->executeHook('text', '>'));
        self::assertSame('unchanged', $manager->executeHook('missing', 'unchanged'));
    }

    public function testActivateFailureDoesNotRegisterThePluginOrItsHooks(): void
    {
        $manager = new PluginManager();
        $plugin = new class ($manager) implements PluginInterface {
            public function __construct(private PluginManager $plugins) {}

            #[\Override]
            public function getName(): string
            {
                return 'bad';
            }

            #[\Override]
            public function getVersion(): string
            {
                return '1';
            }

            #[\Override]
            public function activate(): void
            {
                $this->plugins->addHook('text', static fn(mixed $value): string => Value::string($value) . '!');
                throw new \RuntimeException('activate failed');
            }

            #[\Override]
            public function deactivate(): void {}
        };

        try {
            $manager->register('bad', $plugin);
            self::fail('A failing plugin was registered');
        } catch (\RuntimeException $e) {
            self::assertSame('activate failed', $e->getMessage());
        }

        self::assertFalse($manager->isActive('bad'));
        self::assertSame('kept', $manager->executeHook('text', 'kept'));
    }

    public function testUnregisterRemovesHooksAddedDuringActivate(): void
    {
        $manager = new PluginManager();
        $plugin = new class ($manager) implements PluginInterface {
            public function __construct(private PluginManager $plugins) {}

            #[\Override]
            public function getName(): string
            {
                return 'stats';
            }

            #[\Override]
            public function getVersion(): string
            {
                return '1';
            }

            #[\Override]
            public function activate(): void
            {
                $this->plugins->addHook('text', static fn(mixed $value): string => Value::string($value) . '!');
            }

            #[\Override]
            public function deactivate(): void {}
        };

        $manager->addHook('text', static fn(mixed $value): string => Value::string($value) . '?');
        $manager->register('stats', $plugin);
        self::assertSame('kept?!', $manager->executeHook('text', 'kept'));

        $manager->unregister('stats');
        self::assertSame('kept?', $manager->executeHook('text', 'kept'));
    }

    /**
     * @return PluginInterface&object{calls: list<string>}
     */
    private static function plugin(): PluginInterface
    {
        return new class implements PluginInterface {
            /** @var list<string> */
            public array $calls = [];

            #[\Override]
            public function getName(): string
            {
                return 'stats';
            }

            #[\Override]
            public function getVersion(): string
            {
                return '1.0.0';
            }

            #[\Override]
            public function activate(): void
            {
                $this->calls[] = 'activate';
            }

            #[\Override]
            public function deactivate(): void
            {
                $this->calls[] = 'deactivate';
            }
        };
    }
}
