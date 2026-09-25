<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use stdClass;
use TGbotPHP\Cache\ArrayCache;
use TGbotPHP\Core\Config;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Exceptions\InvalidTokenException;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Plugin\BotPluginInterface;
use TGbotPHP\Support\Value;
use TGbotPHP\Testing\FakeTransport;
use TGbotPHP\Tests\Support\HandlerFailure;
use TGbotPHP\Tests\Support\Updates;
use TGbotPHP\Utilities\BotBuilder;

final class BotTest extends TestCase
{
    private FakeTransport $transport;
    private Bot $bot;

    #[\Override]
    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->bot = new Bot(Updates::TOKEN, transport: $this->transport);
    }

    public function testInvalidTokenThrows(): void
    {
        $this->expectException(InvalidTokenException::class);

        $bot = new Bot('invalid');
        self::fail('Accepted invalid token: ' . $bot->getToken());
    }

    public function testCommandRegisteredWithoutSlashMatches(): void
    {
        $received = [];
        $this->bot->command('start', function (stdClass $message, Bot $bot, string $args) use (&$received): void {
            $received = ['text' => $message->text, 'bot' => $bot, 'args' => $args];
        });

        $this->bot->handleUpdate(Updates::json(Updates::message('/start ref_42')));

        self::assertSame('/start ref_42', $received['text'] ?? null);
        self::assertSame($this->bot, $received['bot'] ?? null);
        self::assertSame('ref_42', $received['args'] ?? null);
    }

    public function testCommandsAreCaseInsensitiveAndAcceptMultilineArgs(): void
    {
        $args = null;
        $this->bot->command('/Echo', function (stdClass $message, Bot $bot, string $a) use (&$args): void {
            self::assertSame($this->bot, $bot);
            self::assertStringStartsWith('/ECHO', Value::string(Value::path($message, 'text')));
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

        $known = 0;
        $this->bot->command('known', function () use (&$known): void {
            $known++;
        });

        $this->bot->handleUpdate(Updates::message('/nope'));
        self::assertSame('/nope', $unknown);

        // Registered commands never reach the unknown command handler
        $this->bot->handleUpdate(Updates::message('/known'));
        self::assertSame(1, $known);
    }

    public function testHearsWithRegexAndFallback(): void
    {
        $log = [];
        $this->bot->hears('/^my name is (\w+)$/i', function (stdClass $message, Bot $bot, array $matches) use (&$log): void {
            self::assertSame($this->bot, $bot);
            $log[] = 'name:' . Value::string($matches[1]) . ' in ' . Value::string(Value::path($message, 'chat', 'type'));
        });
        $this->bot->hears('hello', function () use (&$log): void {
            $log[] = 'hello';
        });
        $this->bot->fallback(function (stdClass $message) use (&$log): void {
            $log[] = 'fallback:' . Value::string(Value::path($message, 'text'));
        });

        $this->bot->handleUpdate(Updates::message('My name is Ada'));
        $this->bot->handleUpdate(Updates::message('hello'));
        $this->bot->handleUpdate(Updates::message('something else'));

        self::assertSame(['name:Ada in private', 'hello', 'fallback:something else'], $log);
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
            $log[] = Value::string(Value::path($cb, 'data'));
        });
        $this->bot->callback('page:*', function (stdClass $cb, Bot $bot, array $m) use (&$log): void {
            self::assertSame($this->bot, $bot);
            self::assertSame('cbq-1', Value::path($cb, 'id'));
            $log[] = 'page ' . Value::string($m[1]);
        });
        $this->bot->callback('#^item:(\d+):(\w+)$#', function (stdClass $cb, Bot $bot, array $m) use (&$log): void {
            self::assertSame($this->bot, $bot);
            self::assertSame(Value::path($cb, 'data'), $m[0]);
            $log[] = 'item ' . Value::string($m[1]) . ' ' . Value::string($m[2]);
        });

        $this->bot->handleUpdate(Updates::callback('menu'));
        $this->bot->handleUpdate(Updates::callback('page:3'));
        $this->bot->handleUpdate(Updates::callback('item:7:buy'));
        $this->bot->handleUpdate(Updates::callback('unhandled'));

        self::assertSame(['menu', 'page 3', 'item 7 buy'], $log);
    }

    public function testNumericCallbackDataAndCommands(): void
    {
        // PHP turns numeric-string array keys into integers: routing must still work
        $log = [];
        $this->bot->callback('123', function () use (&$log): void {
            $log[] = 'callback';
        });
        $this->bot->command('2024', function () use (&$log): void {
            $log[] = 'command';
        });

        $this->bot->handleUpdate(Updates::callback('123'));
        $this->bot->handleUpdate(Updates::message('/2024'));

        self::assertSame(['callback', 'command'], $log);
    }

    public function testNumericCallbackDataThroughBuilder(): void
    {
        $log = [];
        $bot = (new BotBuilder(Updates::TOKEN))
            ->withTransport($this->transport)
            ->addCallback('42', function () use (&$log): void {
                $log[] = 'callback';
            })
            ->build();

        $bot->handleUpdate(Updates::callback('42'));

        self::assertSame(['callback'], $log);
    }

    public function testEditMessageOfCallbackQuery(): void
    {
        $this->bot->callback('edit', fn(stdClass $callback, Bot $bot) => $bot->edit($callback, 'Edited', [
            'reply_markup' => ['inline_keyboard' => []],
        ]));

        $this->bot->handleUpdate(Updates::callback('edit', chatId: 99));

        $request = $this->transport->lastRequest();
        self::assertSame('editMessageText', $request['method']);
        self::assertSame('99', $request['fields']['chat_id'] ?? null);
        self::assertSame('5', $request['fields']['message_id'] ?? null);
        self::assertSame('Edited', $request['fields']['text'] ?? null);
        self::assertSame('{"inline_keyboard":[]}', $request['fields']['reply_markup'] ?? null);
    }

    public function testEditToTheSameContentIsNotAnError(): void
    {
        $this->transport->queueError(400, 'Bad Request: message is not modified: specified new message content and reply markup are exactly the same');
        $result = null;
        $this->bot->callback('same', function (stdClass $callback, Bot $bot) use (&$result): void {
            $result = $bot->edit($callback, 'Same text');
        });

        $this->bot->handleUpdate(Updates::callback('same'));

        self::assertFalse($result);
    }

    public function testEditErrorsOtherThanNotModifiedAreThrown(): void
    {
        $this->transport->queueError(400, 'Bad Request: message to edit not found');
        $this->bot->callback('gone', fn(stdClass $callback, Bot $bot) => $bot->edit($callback, 'Text'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('message to edit not found');

        $this->bot->handleUpdate(Updates::callback('gone'));
    }

    public function testEditInlineMessageOfCallbackQuery(): void
    {
        $this->transport->queueResult(true);
        $update = Updates::callback('edit');
        $callback = Value::map($update['callback_query']);
        unset($callback['message']);
        $callback['inline_message_id'] = 'inline-1';
        $update['callback_query'] = $callback;

        $this->bot->callback('edit', fn(stdClass $callback, Bot $bot) => $bot->edit($callback, 'Edited'));
        $this->bot->handleUpdate($update);

        $fields = $this->transport->lastRequest()['fields'];
        self::assertSame('inline-1', $fields['inline_message_id'] ?? null);
        self::assertArrayNotHasKey('chat_id', $fields);
        self::assertArrayNotHasKey('message_id', $fields);
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
        /** @var \ArrayObject<string, mixed> $received */
        $received = new \ArrayObject();
        $this->bot->onUpdate('pre_checkout_query', function (stdClass $query, Bot $bot, stdClass $update) use ($received): void {
            self::assertSame($this->bot, $bot);
            $received['query'] = $query->id;
            $received['update'] = $update->update_id;
        });

        $this->bot->handleUpdate([
            'update_id' => 99,
            'pre_checkout_query' => ['id' => 'pcq', 'from' => ['id' => 1], 'currency' => 'XTR', 'total_amount' => 1, 'invoice_payload' => 'p'],
        ]);

        self::assertSame(['query' => 'pcq', 'update' => 99], $received->getArrayCopy());
    }

    public function testMessageTypeHandlerRunsWhenNoRouteMatched(): void
    {
        $types = [];
        $this->bot->command('start', function () use (&$types): void {
            $types[] = 'command';
        });
        $this->bot->onUpdate('message', function (stdClass $message) use (&$types): void {
            $types[] = isset($message->sticker) ? 'sticker' : 'other';
        });

        $this->bot->handleUpdate(Updates::message('/start'));
        $this->bot->handleUpdate(Updates::message('', extra: ['text' => null, 'sticker' => ['file_id' => 's']]));

        self::assertSame(['command', 'sticker'], $types);
    }

    public function testSimpleMiddlewareCanStopProcessing(): void
    {
        /** @var \ArrayObject<int, string> $log */
        $log = new \ArrayObject();
        $this->bot->middleware(fn(stdClass $update) => Value::path($update, 'message', 'from', 'id') !== 666);
        $this->bot->command('start', function () use ($log): void {
            $log[] = 'handled';
        });
        $this->bot->on('update.processed', function () use ($log): void {
            $log[] = 'processed';
        });

        $this->bot->handleUpdate(Updates::message('/start', userId: 666));

        self::assertSame([], $log->getArrayCopy());

        $this->bot->handleUpdate(Updates::message('/start'));

        self::assertSame(['handled', 'processed'], $log->getArrayCopy());
    }

    public function testOnionMiddlewareWrapsHandlers(): void
    {
        $log = [];
        $this->bot->middleware(function (stdClass $update, Bot $bot, callable $next) use (&$log): void {
            self::assertSame($this->bot, $bot);
            $log[] = 'before ' . Value::string(Value::path($update, 'message', 'text'));
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

        self::assertSame(['before /start', 'simple', 'handler', 'after'], $log);
    }

    public function testErrorsAreRethrownWithoutListeners(): void
    {
        $this->bot->command('boom', function (): void {
            throw new HandlerFailure('boom');
        });

        $this->expectException(HandlerFailure::class);

        $this->bot->handleUpdate(Updates::message('/boom'));
    }

    public function testErrorsGoToErrorListeners(): void
    {
        $this->transport->queueError(403, 'Forbidden: bot was blocked by the user');
        $errors = [];
        $this->bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'hi'));
        $this->bot->on('error.api', function (ApiException $e) use (&$errors): void {
            $errors[] = 'api ' . $e->getCode();
        });
        $this->bot->onError(function (\Throwable $e, ?stdClass $update, Bot $bot) use (&$errors): void {
            self::assertSame($this->bot, $bot);
            $errors[] = $e->getMessage() . ' #' . Value::int(Value::path($update, 'update_id'));
        });

        $update = Updates::message('/start');
        $this->bot->handleUpdate($update);

        self::assertSame(['api 403', 'Forbidden: bot was blocked by the user #' . Value::int($update['update_id'])], $errors);
    }

    public function testReplySendsToSameChatAndTopic(): void
    {
        $this->bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, '<b>Hi</b>', [
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

    public function testReplyToBusinessMessageUsesTheConnection(): void
    {
        $this->bot->onUpdate('business_message', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hi'));

        $message = Value::map(Updates::message('hello', chatId: 55)['message']);
        $this->bot->handleUpdate(['update_id' => 3, 'business_message' => ['business_connection_id' => 'bc-1'] + $message]);

        $fields = $this->transport->lastRequest()['fields'];
        self::assertSame('55', $fields['chat_id']);
        self::assertSame('bc-1', $fields['business_connection_id']);
        self::assertArrayNotHasKey('message_thread_id', $fields);
    }

    public function testReplyInChannelDirectMessagesUsesTheTopic(): void
    {
        $this->bot->fallback(fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hi', ['direct_messages_topic_id' => 1]));
        $this->bot->hears('topic', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hi'));

        $this->bot->handleUpdate(Updates::message('topic', chatId: -100600, extra: ['direct_messages_topic' => ['topic_id' => 9]]));
        self::assertSame('9', $this->transport->lastRequest()['fields']['direct_messages_topic_id']);

        // Explicit options win
        $this->bot->handleUpdate(Updates::message('other', chatId: -100600, extra: ['direct_messages_topic' => ['topic_id' => 9]]));
        self::assertSame('1', $this->transport->lastRequest()['fields']['direct_messages_topic_id']);
    }

    public function testReplyAndAnswerFromCallbackQuery(): void
    {
        $this->bot->callback('ok', function (stdClass $cb, Bot $bot): void {
            $bot->answer($cb, 'Saved');
            $bot->reply($cb, 'Done');
        });

        $this->bot->handleUpdate(Updates::callback('ok', chatId: 77));

        self::assertSame(['answerCallbackQuery', 'sendMessage'], $this->transport->methods());
        self::assertSame('cbq-1', $this->transport->requests()[0]['fields']['callback_query_id']);
        self::assertSame('77', $this->transport->requests()[1]['fields']['chat_id']);
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
            self::assertSame([], $data);
            $bot->setState($message, 'ask_age', ['name' => $message->text]);
        });
        $this->bot->state('ask_age', function (stdClass $message, Bot $bot, array $data) use (&$log): void {
            $log[] = Value::string($data['name'] ?? null) . ' is ' . Value::string(Value::path($message, 'text'));
            $bot->clearState($message);
        });
        $this->bot->fallback(function (stdClass $message) use (&$log): void {
            $log[] = 'fallback:' . Value::string(Value::path($message, 'text'));
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
            $states[] = Value::path($m, 'from', 'id');
        });
        $this->bot->fallback(function (stdClass $m) use (&$states): void {
            $states[] = 'fallback:' . Value::int(Value::path($m, 'from', 'id'));
        });

        $this->bot->handleUpdate(Updates::message('/go', chatId: 1, userId: 10));
        $this->bot->handleUpdate(Updates::message('hi', chatId: 1, userId: 20));
        $this->bot->handleUpdate(Updates::message('hi', chatId: 1, userId: 10));

        self::assertSame(['fallback:20', 10], $states);
    }

    public function testHandleCanRespondBeforeRunningHandlers(): void
    {
        $handled = false;
        $this->bot->command('start', function () use (&$handled): void {
            $handled = true;
        });

        // Outside PHP-FPM there is no response to finish: the update is still processed
        self::assertTrue($this->bot->handle(Updates::json(Updates::message('/start')), respondFirst: true));
        self::assertTrue($handled);
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
            throw new HandlerFailure('boom');
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
        self::assertSame((string) (Value::int($second['update_id']) + 1), $this->transport->lastRequest()['fields']['offset']);
        self::assertFalse($this->bot->isRunning());
    }

    public function testPollingCanBeStoppedFromHandler(): void
    {
        $this->bot->setUsername('poll_bot');
        $this->transport->queueResult([Updates::message('/stop'), Updates::message('/stop')]);

        $count = 0;
        $this->bot->command('stop', function (stdClass $message, Bot $bot) use (&$count): void {
            self::assertSame('/stop', Value::path($message, 'text'));
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

            #[\Override]
            public function getName(): string
            {
                return 'ping';
            }

            #[\Override]
            public function getVersion(): string
            {
                return '1.0.0';
            }

            #[\Override]
            public function activate(): void
            {
                $this->active = true;
            }

            #[\Override]
            public function deactivate(): void
            {
                $this->active = false;
            }

            #[\Override]
            public function boot(Bot $bot): void
            {
                $bot->command('ping', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'pong'));
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
            $log,
        );
    }

    public function testConversationStatesArePerChatAndUser(): void
    {
        $this->bot->useConversations(new ArrayCache());

        $message = Value::object(UpdateParser::fromArray(Updates::message('x', chatId: 1, userId: 23))->message ?? null);
        self::assertNotNull($message);
        $this->bot->setState($message, 'first');

        $manager = $this->bot->conversations();
        self::assertSame('first', $manager->getState(1, 23));
        self::assertNull($manager->getState(12, 3), 'chat 1 + user 23 is not chat 12 + user 3');
        self::assertNull($manager->getState(2, 23), 'Same user in another chat');
    }

    public function testConversationsMustBeEnabled(): void
    {
        $this->expectException(\LogicException::class);

        $this->bot->conversations();
    }

    public function testEditNeedsAMessage(): void
    {
        $callback = UpdateParser::fromArray(['update_id' => 1, 'callback_query' => ['id' => 'q', 'from' => ['id' => 1], 'data' => 'x']])->callback_query;
        self::assertInstanceOf(stdClass::class, $callback);

        $this->expectException(\InvalidArgumentException::class);

        $this->bot->edit($callback, 'text');
    }

    public function testStopAndIsRunningBeforePolling(): void
    {
        $this->bot->stop();

        self::assertFalse($this->bot->isRunning());
    }

    public function testHandleUpdateAcceptsObjectsArraysAndJson(): void
    {
        $received = [];
        $this->bot->on('update.received', function (stdClass $update) use (&$received): void {
            $received[] = $update->update_id;
        });

        $this->bot->handleUpdate(UpdateParser::fromArray(['update_id' => 1] + Updates::message('a')));
        $this->bot->handleUpdate(['update_id' => 2] + Updates::message('b'));
        $this->bot->handleUpdate(Updates::json(['update_id' => 3] + Updates::message('c')));

        self::assertSame([1, 2, 3], $received);
    }

    public function testMalformedPayloadsAreIgnored(): void
    {
        $this->bot->fallback(fn() => self::fail('A malformed message reached a handler'));

        $this->bot->handleUpdate(['update_id' => 1, 'message' => 'not an object']);
        $this->bot->handleUpdate(['update_id' => 2]);

        self::assertSame([], $this->transport->requests());
    }

    public function testExactCallbackPatternsReceiveTheData(): void
    {
        $matches = null;
        $this->bot->callback('menu', function (stdClass $callback, Bot $bot, array $m) use (&$matches): void {
            $matches = $m;
        });

        $this->bot->handleUpdate(Updates::callback('menu'));

        self::assertSame(['menu'], $matches);
    }

    public function testHandleReadsTheSecretFromTheRequest(): void
    {
        $bot = new Bot(new Config(Updates::TOKEN, secretToken: 'top-secret'), transport: $this->transport);
        $body = Updates::json(Updates::message('/start'));

        $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = 'top-secret';
        try {
            self::assertTrue($bot->handle($body));
            $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = 'wrong';
            self::assertFalse($bot->handle($body));
        } finally {
            unset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']);
        }
    }
}
