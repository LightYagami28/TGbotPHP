<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use stdClass;
use TGbotPHP\Cache\ArrayCache;
use TGbotPHP\Core\Config;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Exceptions\InvalidTokenException;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Plugin\BotPluginInterface;
use TGbotPHP\Tests\Support\FakeTransport;
use TGbotPHP\Tests\Support\Updates;
use TGbotPHP\Utilities\BotBuilder;

final class BotTest extends TestCase
{
    private FakeTransport $transport;
    private Bot $bot;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->bot = new Bot(Updates::TOKEN, transport: $this->transport);
    }

    public function testInvalidTokenThrows(): void
    {
        $this->expectException(InvalidTokenException::class);

        self::assertInstanceOf(Bot::class, new Bot('invalid'));
    }

    public function testCommandRegisteredWithoutSlashMatches(): void
    {
        $received = null;
        $this->bot->command('start', function (stdClass $message, Bot $bot, string $args) use (&$received): void {
            $received = [$message->text, $bot, $args];
        });

        $this->bot->handleUpdate(Updates::json(Updates::message('/start ref_42')));

        self::assertSame(['/start ref_42', $this->bot, 'ref_42'], $received);
    }

    public function testCommandsAreCaseInsensitiveAndAcceptMultilineArgs(): void
    {
        $args = null;
        $this->bot->command('/Echo', function ($message, $bot, string $a) use (&$args): void {
            $args = $a;
        });

        $this->bot->handleUpdate(Updates::message("/ECHO line one\nline two"));

        self::assertSame("line one\nline two", $args);
    }

    public function testCommandsAddressedToOtherBotsAreIgnored(): void
    {
        $calls = 0;
        $this->bot->setUsername('@My_Bot');
        $this->bot->command('help', function () use (&$calls): void {
            $calls++;
        });
        $this->bot->fallback(function () use (&$calls): void {
            $calls += 100;
        });

        $this->bot->handleUpdate(Updates::message('/help@my_bot'));
        $this->bot->handleUpdate(Updates::message('/help@other_bot'));

        self::assertSame(1, $calls);
    }

    public function testUnknownCommandHandler(): void
    {
        $unknown = null;
        $this->bot->onUnknownCommand(function (stdClass $message) use (&$unknown): void {
            $unknown = $message->text;
        });

        $this->bot->handleUpdate(Updates::message('/nope'));

        self::assertSame('/nope', $unknown);
    }

    public function testHearsWithRegexAndFallback(): void
    {
        $log = [];
        $this->bot->hears('/^my name is (\w+)$/i', function ($message, $bot, array $matches) use (&$log): void {
            $log[] = 'name:' . $matches[1];
        });
        $this->bot->hears('hello', function () use (&$log): void {
            $log[] = 'hello';
        });
        $this->bot->fallback(function (stdClass $message) use (&$log): void {
            $log[] = 'fallback:' . $message->text;
        });

        $this->bot->handleUpdate(Updates::message('My name is Ada'));
        $this->bot->handleUpdate(Updates::message('hello'));
        $this->bot->handleUpdate(Updates::message('something else'));

        self::assertSame(['name:Ada', 'hello', 'fallback:something else'], $log);
    }

    public function testFallbackIgnoresMessagesWithoutText(): void
    {
        $called = false;
        $this->bot->fallback(function () use (&$called): void {
            $called = true;
        });

        $this->bot->handleUpdate(Updates::message('', extra: ['text' => null, 'photo' => [['file_id' => 'x']]]));

        self::assertFalse($called);
    }

    public function testCallbackExactWildcardAndRegex(): void
    {
        $log = [];
        $this->bot->callback('menu', function (stdClass $cb) use (&$log): void {
            $log[] = 'menu';
        });
        $this->bot->callback('page:*', function ($cb, $bot, array $m) use (&$log): void {
            $log[] = 'page ' . $m[1];
        });
        $this->bot->callback('#^item:(\d+):(\w+)$#', function ($cb, $bot, array $m) use (&$log): void {
            $log[] = "item {$m[1]} {$m[2]}";
        });

        $this->bot->handleUpdate(Updates::callback('menu'));
        $this->bot->handleUpdate(Updates::callback('page:3'));
        $this->bot->handleUpdate(Updates::callback('item:7:buy'));
        $this->bot->handleUpdate(Updates::callback('unhandled'));

        self::assertSame(['menu', 'page 3', 'item 7 buy'], $log);
    }

    public function testInlineQueryCatchAll(): void
    {
        $query = null;
        $this->bot->inlineQuery('*', function (stdClass $inline) use (&$query): void {
            $query = $inline->query;
        });

        $this->bot->handleUpdate(Updates::inlineQuery('cats'));

        self::assertSame('cats', $query);
    }

    public function testUpdateTypeHandlers(): void
    {
        $received = null;
        $this->bot->onUpdate('pre_checkout_query', function (stdClass $query, Bot $bot, stdClass $update) use (&$received): void {
            $received = [$query->id, $update->update_id];
        });

        $this->bot->handleUpdate([
            'update_id' => 99,
            'pre_checkout_query' => ['id' => 'pcq', 'from' => ['id' => 1], 'currency' => 'XTR', 'total_amount' => 1, 'invoice_payload' => 'p'],
        ]);

        self::assertSame(['pcq', 99], $received);
    }

    public function testMessageTypeHandlerRunsWhenNoRouteMatched(): void
    {
        $types = [];
        $this->bot->command('start', function () use (&$types): void {
            $types[] = 'command';
        });
        $this->bot->onUpdate('message', function (stdClass $message) use (&$types): void {
            $types[] = 'any';
        });

        $this->bot->handleUpdate(Updates::message('/start'));
        $this->bot->handleUpdate(Updates::message('', extra: ['text' => null, 'sticker' => ['file_id' => 's']]));

        self::assertSame(['command', 'any'], $types);
    }

    public function testSimpleMiddlewareCanStopProcessing(): void
    {
        $handled = false;
        $processed = false;
        $this->bot->middleware(fn(stdClass $update) => $update->message->from->id !== 666);
        $this->bot->command('start', function () use (&$handled): void {
            $handled = true;
        });
        $this->bot->on('update.processed', function () use (&$processed): void {
            $processed = true;
        });

        $this->bot->handleUpdate(Updates::message('/start', userId: 666));

        self::assertFalse($handled);
        self::assertFalse($processed);

        $this->bot->handleUpdate(Updates::message('/start'));

        self::assertTrue($handled);
        self::assertTrue($processed);
    }

    public function testOnionMiddlewareWrapsHandlers(): void
    {
        $log = [];
        $this->bot->middleware(function (stdClass $update, Bot $bot, callable $next) use (&$log): void {
            $log[] = 'before';
            $next();
            $log[] = 'after';
        });
        $this->bot->middleware(function () use (&$log): void {
            $log[] = 'simple';
        });
        $this->bot->command('start', function () use (&$log): void {
            $log[] = 'handler';
        });

        $this->bot->handleUpdate(Updates::message('/start'));

        self::assertSame(['before', 'simple', 'handler', 'after'], $log);
    }

    public function testErrorsAreRethrownWithoutListeners(): void
    {
        $this->bot->command('boom', function (): void {
            throw new \RuntimeException('boom');
        });

        $this->expectException(\RuntimeException::class);

        $this->bot->handleUpdate(Updates::message('/boom'));
    }

    public function testErrorsGoToErrorListeners(): void
    {
        $this->transport->queueError(403, 'Forbidden: bot was blocked by the user');
        $errors = [];
        $this->bot->command('start', fn($message, Bot $bot) => $bot->reply($message, 'hi'));
        $this->bot->on('error.api', function (ApiException $e) use (&$errors): void {
            $errors[] = 'api';
        });
        $this->bot->onError(function (\Throwable $e, ?stdClass $update, Bot $bot) use (&$errors): void {
            $errors[] = $e->getMessage() . ' #' . $update?->update_id;
        });

        $update = Updates::message('/start');
        $this->bot->handleUpdate($update);

        self::assertSame(['api', 'Forbidden: bot was blocked by the user #' . $update['update_id']], $errors);
    }

    public function testReplySendsToSameChatAndTopic(): void
    {
        $this->bot->command('start', fn($message, Bot $bot) => $bot->reply($message, '<b>Hi</b>', [
            'reply_markup' => ['remove_keyboard' => true],
        ]));

        $this->bot->handleUpdate(Updates::message('/start', chatId: -100500, extra: ['is_topic_message' => true, 'message_thread_id' => 12]));

        $fields = $this->transport->lastRequest()['fields'];
        self::assertSame('sendMessage', $this->transport->lastRequest()['method']);
        self::assertSame('-100500', $fields['chat_id']);
        self::assertSame('12', $fields['message_thread_id']);
        self::assertSame('HTML', $fields['parse_mode']);
        self::assertSame('{"remove_keyboard":true}', $fields['reply_markup']);
    }

    public function testReplyAndAnswerFromCallbackQuery(): void
    {
        $this->bot->callback('ok', function (stdClass $cb, Bot $bot): void {
            $bot->answer($cb, 'Saved');
            $bot->reply($cb, 'Done');
        });

        $this->bot->handleUpdate(Updates::callback('ok', chatId: 77));

        self::assertSame(['answerCallbackQuery', 'sendMessage'], $this->transport->methods());
        self::assertSame('cbq-1', $this->transport->requests[0]['fields']['callback_query_id']);
        self::assertSame('77', $this->transport->requests[1]['fields']['chat_id']);
    }

    public function testConversationStates(): void
    {
        $this->bot->useConversations(new ArrayCache());
        $log = [];

        $this->bot->command('register', function (stdClass $message, Bot $bot): void {
            $bot->setState($message, 'ask_name');
        });
        $this->bot->command('cancel', function (stdClass $message, Bot $bot) use (&$log): void {
            $bot->clearState($message);
            $log[] = 'cancelled';
        });
        $this->bot->state('ask_name', function (stdClass $message, Bot $bot, array $data): void {
            $bot->setState($message, 'ask_age', ['name' => $message->text]);
        });
        $this->bot->state('ask_age', function (stdClass $message, Bot $bot, array $data) use (&$log): void {
            $log[] = "{$data['name']} is {$message->text}";
            $bot->clearState($message);
        });
        $this->bot->fallback(function (stdClass $message) use (&$log): void {
            $log[] = 'fallback:' . $message->text;
        });

        $this->bot->handleUpdate(Updates::message('/register'));
        $this->bot->handleUpdate(Updates::message('Ada'));
        $this->bot->handleUpdate(Updates::message('36'));
        $this->bot->handleUpdate(Updates::message('after'));

        $this->bot->handleUpdate(Updates::message('/register'));
        $this->bot->handleUpdate(Updates::message('/cancel'));
        $this->bot->handleUpdate(Updates::message('free text'));

        self::assertSame(['Ada is 36', 'fallback:after', 'cancelled', 'fallback:free text'], $log);
    }

    public function testConversationsAreScopedPerUser(): void
    {
        $this->bot->useConversations(new ArrayCache());
        $states = [];
        $this->bot->command('go', fn(stdClass $m, Bot $bot) => $bot->setState($m, 'waiting'));
        $this->bot->state('waiting', function (stdClass $m) use (&$states): void {
            $states[] = $m->from->id;
        });
        $this->bot->fallback(function (stdClass $m) use (&$states): void {
            $states[] = 'fallback:' . $m->from->id;
        });

        $this->bot->handleUpdate(Updates::message('/go', chatId: 1, userId: 10));
        $this->bot->handleUpdate(Updates::message('hi', chatId: 1, userId: 20));
        $this->bot->handleUpdate(Updates::message('hi', chatId: 1, userId: 10));

        self::assertSame(['fallback:20', 10], $states);
    }

    public function testHandleValidatesSecretToken(): void
    {
        $bot = new Bot(new Config(Updates::TOKEN, secretToken: 'top-secret'), transport: $this->transport);
        $calls = 0;
        $bot->command('start', function () use (&$calls): void {
            $calls++;
        });
        $body = Updates::json(Updates::message('/start'));

        self::assertFalse($bot->handle($body, null));
        self::assertFalse($bot->handle($body, 'wrong'));
        self::assertTrue($bot->handle($body, 'top-secret'));
        self::assertSame(1, $calls);
    }

    public function testHandleRejectsInvalidJson(): void
    {
        self::assertFalse($this->bot->handle('{not json', null));
        self::assertFalse($this->bot->handle('{"foo":1}', null));
    }

    public function testHandleSwallowsHandlerErrors(): void
    {
        $this->bot->command('boom', function (): void {
            throw new \RuntimeException('boom');
        });

        $previous = ini_set('error_log', '/dev/null');
        try {
            self::assertTrue($this->bot->handle(Updates::json(Updates::message('/boom')), null));
        } finally {
            ini_set('error_log', (string) $previous);
        }
    }

    public function testPollingProcessesUpdatesAndAcknowledgesOffset(): void
    {
        $first = Updates::message('/start');
        $second = Updates::message('hello');

        $this->transport
            ->queueResult(['id' => 1, 'is_bot' => true, 'first_name' => 'B', 'username' => 'poll_bot'])
            ->queueResult([$first, $second])
            ->queueResult([]);

        $seen = [];
        $this->bot->command('start', function () use (&$seen): void {
            $seen[] = 'start';
        });
        $this->bot->fallback(function (stdClass $m) use (&$seen): void {
            $seen[] = $m->text;
        });

        $this->bot->poll(timeout: 0, maxIterations: 1);

        self::assertSame(['start', 'hello'], $seen);
        self::assertSame('poll_bot', $this->bot->getUsername());
        self::assertSame(['getMe', 'getUpdates', 'getUpdates'], $this->transport->methods());
        self::assertSame((string) ($second['update_id'] + 1), $this->transport->lastRequest()['fields']['offset']);
        self::assertFalse($this->bot->isRunning());
    }

    public function testPollingCanBeStoppedFromHandler(): void
    {
        $this->bot->setUsername('poll_bot');
        $this->transport->queueResult([Updates::message('/stop'), Updates::message('/stop')]);

        $count = 0;
        $this->bot->command('stop', function ($message, Bot $bot) use (&$count): void {
            $count++;
            $bot->stop();
        });

        $this->bot->poll(timeout: 0);

        self::assertSame(1, $count);
    }

    public function testPollingStopsOnConflict(): void
    {
        $this->bot->setUsername('poll_bot');
        $this->transport->queueError(409, 'Conflict: can\'t use getUpdates method while webhook is active');

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(409);

        $this->bot->poll(timeout: 0);
    }

    public function testPluginsAreBooted(): void
    {
        $plugin = new class implements BotPluginInterface {
            public bool $active = false;

            public function getName(): string
            {
                return 'ping';
            }

            public function getVersion(): string
            {
                return '1.0.0';
            }

            public function activate(): void
            {
                $this->active = true;
            }

            public function deactivate(): void
            {
                $this->active = false;
            }

            public function boot(Bot $bot): void
            {
                $bot->command('ping', fn($message, Bot $bot) => $bot->reply($message, 'pong'));
            }
        };

        $this->bot->plugin($plugin);
        $this->bot->handleUpdate(Updates::message('/ping'));

        self::assertTrue($plugin->active);
        self::assertTrue($this->bot->getPlugins()->isActive('ping'));
        self::assertSame('pong', $this->transport->lastRequest()['fields']['text']);
    }

    public function testBuilderConfiguresBot(): void
    {
        $log = [];
        $bot = (new BotBuilder(Updates::TOKEN))
            ->withTransport($this->transport)
            ->withUsername('built_bot')
            ->addCommand('start', function () use (&$log): void {
                $log[] = 'start';
            })
            ->addCallback('a:*', function () use (&$log): void {
                $log[] = 'callback';
            })
            ->addText('/^hi$/', function () use (&$log): void {
                $log[] = 'text';
            })
            ->addMiddleware(function () use (&$log): void {
                $log[] = 'middleware';
            })
            ->addEventListener('update.processed', function () use (&$log): void {
                $log[] = 'processed';
            })
            ->build();

        $bot->handleUpdate(Updates::message('/start@built_bot'));
        $bot->handleUpdate(Updates::callback('a:1'));
        $bot->handleUpdate(Updates::message('hi'));

        self::assertSame('built_bot', $bot->getUsername());
        self::assertSame(
            ['middleware', 'start', 'processed', 'middleware', 'callback', 'processed', 'middleware', 'text', 'processed'],
            $log
        );
    }
}
