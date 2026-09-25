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
