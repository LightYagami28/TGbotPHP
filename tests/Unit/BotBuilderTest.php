<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use stdClass;
use TGbotPHP\Cache\ArrayCache;
use TGbotPHP\Core\RetryPolicy;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Plugin\BotPluginInterface;
use TGbotPHP\Testing\FakeTransport;
use TGbotPHP\Tests\Support\Updates;
use TGbotPHP\Utilities\BotBuilder;

final class BotBuilderTest extends TestCase
{
    public function testBuildsTheConfig(): void
    {
        $retry = new RetryPolicy(maxRetries: 3, maxDelay: 5);

        $config = new BotBuilder(Updates::TOKEN)
            ->withDebug('/var/log/bot.log')
            ->withSecretToken('webhook-secret')
            ->withApiServer('http://127.0.0.1:8081', enforceHttps: false)
            ->withTimeout(60)
            ->withRetry($retry)
            ->build()
            ->getConfig();

        self::assertSame(Updates::TOKEN, $config->token);
        self::assertSame('/var/log/bot.log', $config->debugFile);
        self::assertSame('webhook-secret', $config->secretToken);
        self::assertSame('http://127.0.0.1:8081', $config->apiBaseUrl);
        self::assertSame(60, $config->timeout);
        self::assertSame($retry, $config->retry);
    }

    public function testRegistersEveryKindOfHandler(): void
    {
        $log = [];
        $record = static function (string $entry) use (&$log): \Closure {
            return static function () use (&$log, $entry): void {
                $log[] = $entry;
            };
        };

        $bot = new BotBuilder(Updates::TOKEN)
            ->withTransport(new FakeTransport())
            ->withConversations(new ArrayCache(), ttl: 60)
            ->addCommand('ask', static function (stdClass $message, Bot $bot) use (&$log): void {
                $log[] = 'ask';
                $bot->setState($message, 'answer');
            })
            ->addState('answer', static function (stdClass $message, Bot $bot) use (&$log): void {
                $log[] = 'state';
                $bot->clearState($message);
            })
            ->addInlineQuery('*', $record('inline'))
            ->addUpdateHandler('chat_member', $record('chat_member'))
            ->setFallback($record('fallback'))
            ->build();

        $bot->handleUpdate(Updates::message('/ask'));
        $bot->handleUpdate(Updates::message('42'));
        $bot->handleUpdate(Updates::message('free text'));
        $bot->handleUpdate(Updates::inlineQuery('cats'));
        $bot->handleUpdate(['update_id' => 9, 'chat_member' => ['chat' => ['id' => 1, 'type' => 'group']]]);

        self::assertSame(['ask', 'state', 'fallback', 'inline', 'chat_member'], $log);
    }

    public function testBootsPlugins(): void
    {
        $plugin = new class implements BotPluginInterface {
            public ?string $bootedWith = null;

            #[\Override]
            public function boot(Bot $bot): void
            {
                $this->bootedWith = $bot->getToken();
            }

            #[\Override]
            public function getName(): string
            {
                return 'probe';
            }

            #[\Override]
            public function getVersion(): string
            {
                return '1.0.0';
            }

            #[\Override]
            public function activate(): void
            {
                // Nothing to set up: the test only checks boot()
            }

            #[\Override]
            public function deactivate(): void
            {
                // Nothing to release
            }
        };

        $bot = new BotBuilder(Updates::TOKEN)->addPlugin($plugin)->build();

        self::assertSame(Updates::TOKEN, $plugin->bootedWith);
        self::assertSame($plugin, $bot->getPlugins()->get('probe'));
    }
}
