<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use JsonSerializable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use stdClass;
use TGbotPHP\Cache\ArrayCache;
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Core\Config;
use TGbotPHP\Core\RetryPolicy;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Exceptions\StorageException;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Security\LogRedactor;
use TGbotPHP\Security\WebhookValidator;
use TGbotPHP\Support\Payload;
use TGbotPHP\Support\PrivateFile;
use TGbotPHP\Support\Value;
use TGbotPHP\Testing\FakeTransport;
use TGbotPHP\Testing\FakeUpdate;
use TGbotPHP\Tests\Support\RuntimeControls;
use TGbotPHP\Tests\Support\Updates;
use TGbotPHP\Utilities\Keyboard;
use TGbotPHP\Utilities\MessageParser;

final class FullCoverageTest extends TestCase
{
    public function testStaticClassesCanBeConstructedOnlyForCoverageOfTheGuard(): void
    {
        foreach ([Value::class, Payload::class, PrivateFile::class, LogRedactor::class, FakeUpdate::class] as $class) {
            $reflection = new ReflectionClass($class);
            $instance = $reflection->newInstanceWithoutConstructor();
            $constructor = $reflection->getConstructor();
            self::assertNotNull($constructor);
            self::assertTrue($constructor->isPrivate());
            $constructor->invoke($instance);
        }
    }

    public function testClientAccessorsAndTypedResultFailures(): void
    {
        $transport = new FakeTransport();
        $client = new ApiClient(new Config(Updates::TOKEN, retry: RetryPolicy::none()), $transport);

        self::assertSame('https://api.telegram.org/bot' . Updates::TOKEN, $client->getApiUrl());
        self::assertTrue($client->isValid());
        self::assertSame((int) strstr(Updates::TOKEN, ':', true), $client->getConfig()->getBotId());
        self::assertSame(0, RetryPolicy::none()->transientDelay(0));
        self::assertSame(0, (new RetryPolicy(maxDelay: 5))->transientDelay(-1));

        $transport->queueError(400, 'Bad Request: chat not found');

        try {
            $client->sendMessage(1, 'x');
            self::fail('Expected an API error');
        } catch (ApiException $e) {
            self::assertSame('Bad Request: chat not found', $e->getApiResponse()['description'] ?? null);
        }

        $transport->queueResult(true);
        try {
            $client->getMe();
            self::fail('getMe accepted a boolean');
        } catch (ApiException $e) {
            self::assertStringContainsString('expected an object', $e->getMessage());
        }

        $transport->queueResult('nope');
        try {
            $client->editMessageText(1, 2, 'hi');
            self::fail('editMessageText accepted a string');
        } catch (ApiException $e) {
            self::assertStringContainsString('expected an object or true', $e->getMessage());
        }

        $transport->queueResult(true);
        try {
            $client->getChatMemberCount(1);
            self::fail('getChatMemberCount accepted a boolean');
        } catch (ApiException $e) {
            self::assertStringContainsString('expected an integer', $e->getMessage());
        }

        $transport->queueResult(3);
        self::assertSame(3, $client->getChatMembersCount(1));

        $transport->queueResult(true);
        try {
            $client->exportChatInviteLink(1);
            self::fail('exportChatInviteLink accepted a boolean');
        } catch (ApiException $e) {
            self::assertStringContainsString('expected a string', $e->getMessage());
        }
    }

    public function testPrepareFieldsRejectsUnsupportedValuesAndEncodesJsonSerializable(): void
    {
        $markup = new class implements JsonSerializable {
            /**
             * @return array<string, mixed>
             */
            #[\Override]
            public function jsonSerialize(): array
            {
                return ['inline_keyboard' => []];
            }
        };

        [$fields] = ApiClient::prepareFields(['reply_markup' => $markup]);
        self::assertSame('{"inline_keyboard":[]}', $fields['reply_markup']);

        $this->expectException(\InvalidArgumentException::class);
        ApiClient::prepareFields(['weird' => new stdClass()]);
    }

    public function testDescribeFieldsKeepsNonStringValues(): void
    {
        $method = new ReflectionMethod(ApiClient::class, 'describeFields');
        $described = $method->invoke(null, ['n' => 7]);

        self::assertIsString($described);
        self::assertStringContainsString('7', $described);
    }

    public function testDebugTrueWritesToThePhpErrorLog(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'tgbotphp-err');
        self::assertIsString($log);
        $previous = ini_set('error_log', $log);
        $transport = new FakeTransport();

        try {
            $client = new ApiClient(new Config(Updates::TOKEN, debug: true, retry: RetryPolicy::none()), $transport);
            $client->getMe();
            self::assertStringContainsString('getMe', (string) file_get_contents($log));
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
            @unlink($log);
        }
    }

    public function testRetryAfterSleepsWhenNoSleeperIsInstalled(): void
    {
        $transport = new FakeTransport();
        $client = new ApiClient(
            new Config(Updates::TOKEN, retry: new RetryPolicy(maxRetries: 1, maxDelay: 1)),
            $transport,
        );
        $transport
            ->queueError(429, 'Too Many Requests', ['retry_after' => 1])
            ->queueResult(['message_id' => 1]);

        $started = microtime(true);
        $sent = $client->sendMessage(1, 'x');

        self::assertSame(1, $sent['message_id']);
        self::assertGreaterThanOrEqual(1.0, microtime(true) - $started);
        self::assertCount(2, $transport->requests());
    }

    public function testBotAccessorsConversationsAndWebhookFinishers(): void
    {
        $transport = new FakeTransport();
        $bot = new Bot(new Config(Updates::TOKEN, allowUnauthenticatedWebhook: true), $transport);

        self::assertNull($bot->getUpdate());
        $bot->command('start', static function (): void {});
        self::assertSame(['start'], $bot->getRouter()->getCommands());
        self::assertSame([], $bot->getMiddleware()->getMiddleware());

        $update = UpdateParser::fromArray(Updates::message('/start'));
        $bot->handleUpdate($update);
        self::assertSame($update->update_id, $bot->getUpdate()->update_id ?? null);

        $reached = false;
        $bot->getMiddleware()->add(static function () use (&$reached): bool {
            $reached = true;

            return true;
        });
        self::assertTrue($bot->getMiddleware()->execute(UpdateParser::fromArray(Updates::message('hi'))));
        self::assertTrue($reached);

        $bot->useConversations(new ArrayCache());
        $message = UpdateParser::fromArray(Updates::message('Ada'))->message;
        self::assertInstanceOf(stdClass::class, $message);
        $chat = $message->chat ?? null;
        $from = $message->from ?? null;
        self::assertInstanceOf(stdClass::class, $chat);
        self::assertInstanceOf(stdClass::class, $from);
        $chatId = $chat->id ?? null;
        $userId = $from->id ?? null;
        if ((!is_int($chatId) && !is_string($chatId)) || (!is_int($userId) && !is_string($userId))) {
            self::fail('The message did not carry a chat id and a user id');
        }
        $bot->setState($message, 'ask_name', ['a' => 1]);
        $bot->updateStateData($message, ['b' => 2]);
        self::assertSame(['a' => 1, 'b' => 2], $bot->conversations()->getData($chatId, $userId));
        self::assertSame($chatId, $bot->chatId($message));

        $this->expectException(\InvalidArgumentException::class);
        $bot->answer(new stdClass());
    }

    public function testWebhookCanFinishTheRequestOnFastcgiAndLitespeed(): void
    {
        $bot = new Bot(new Config(Updates::TOKEN, allowUnauthenticatedWebhook: true), new FakeTransport());
        $body = Updates::json(Updates::message('/start'));

        RuntimeControls::$fastcgi = true;
        try {
            self::assertTrue($bot->handle($body, null, respondFirst: true));
            self::assertSame(['fastcgi'], RuntimeControls::$finished);
        } finally {
            RuntimeControls::reset();
        }

        RuntimeControls::$fastcgi = false;
        RuntimeControls::$litespeed = true;
        try {
            self::assertTrue($bot->handle($body, null, respondFirst: true));
            self::assertSame(['litespeed'], RuntimeControls::$finished);
        } finally {
            RuntimeControls::reset();
        }
    }

    public function testWebhookRejectsABodyLimitOfPhpIntMax(): void
    {
        $bot = new Bot(new Config(
            Updates::TOKEN,
            allowUnauthenticatedWebhook: true,
            maxWebhookBodyBytes: PHP_INT_MAX,
        ), new FakeTransport());

        self::assertFalse($bot->handle('{}', null));
        self::assertSame(413, http_response_code());
        http_response_code(200);
    }

    public function testRateLimiterAllowsUpdatesWithoutAUser(): void
    {
        $allowed = (new \TGbotPHP\Rate\RateLimiter(new ArrayCache()))->middleware(1, 60);

        self::assertTrue($allowed(new stdClass()));
    }

    public function testFakeTransportDefaultResultAndMissingRequest(): void
    {
        $transport = new FakeTransport();
        $transport->defaultResult = ['message_id' => 9];
        $client = new ApiClient(new Config(Updates::TOKEN, retry: RetryPolicy::none()), $transport);

        self::assertSame(9, $client->sendMessage(1, 'x')['message_id']);

        $empty = new FakeTransport();
        $this->expectException(\LogicException::class);
        $empty->lastRequest();
    }

    public function testLogRedactorStopsAtNestedDepth(): void
    {
        $nested = 'leaf';
        for ($i = 0; $i < 8; $i++) {
            $nested = ['inner' => $nested];
        }

        $redacted = LogRedactor::context(['top' => $nested]);
        $cursor = $redacted['top'];
        $depth = 0;
        while (is_array($cursor)) {
            $cursor = $cursor['inner'];
            $depth++;
        }
        self::assertSame('<redacted>', $cursor);
        self::assertSame(8, $depth);
    }

    public function testWebAppFreshnessAndSignatureEdges(): void
    {
        $authDate = (string) time();
        $secret = hash_hmac('sha256', Updates::TOKEN, 'WebAppData', true);
        $hash = hash_hmac('sha256', 'auth_date=' . $authDate, $secret);

        self::assertNull(WebhookValidator::validateWebAppData("auth_date=$authDate&hash=$hash", Updates::TOKEN, maxAge: -1));

        $publicKey = str_repeat('ab', 32);
        self::assertNull(WebhookValidator::validateWebAppSignature(
            "auth_date=$authDate&signature=A",
            1,
            publicKey: $publicKey,
        ));

        RuntimeControls::$hideSodium = true;
        try {
            WebhookValidator::validateWebAppSignature('auth_date=1&signature=A', 1);
            self::fail('Validated a signature without sodium');
        } catch (\LogicException $e) {
            self::assertStringContainsString('sodium', $e->getMessage());
        } finally {
            RuntimeControls::reset();
        }
    }

    public function testMessageParserEmptyExtractorsAndKeyboardShortcuts(): void
    {
        self::assertSame([], MessageParser::extractHashtags('no tags'));
        self::assertSame([], MessageParser::extractUrls('no links'));
        self::assertSame([], MessageParser::extractEmails('no mail'));
        self::assertSame(Keyboard::inline(['Yes' => 'yes']), Keyboard::row(['Yes' => 'yes']));
        $row = Keyboard::inlineBuilder()->button('Yes', 'yes')->toArray()['inline_keyboard'][0] ?? null;
        self::assertSame([['text' => 'Yes', 'callback_data' => 'yes']], $row);
    }

    public function testDownloadFailuresAndLocalFileGuards(): void
    {
        $transport = new FakeTransport();
        $client = new ApiClient(new Config(Updates::TOKEN, retry: RetryPolicy::none()), $transport);

        $transport->queueResult(['file_id' => 'f']);
        try {
            $client->downloadFile('f');
            self::fail('Downloaded a file without a path');
        } catch (ApiException $e) {
            self::assertStringContainsString('not available', $e->getMessage());
        }

        $blocked = sys_get_temp_dir() . '/tgbotphp-block-' . bin2hex(random_bytes(2));
        mkdir($blocked);
        $transport->queueResult(['file_id' => 'f', 'file_path' => 'documents/a.pdf']);
        $transport->queueRaw('pdf');
        try {
            $client->downloadFile('f', $blocked);
            self::fail('Renamed a download onto a directory');
        } catch (StorageException $e) {
            self::assertStringContainsString('Unable to write file', $e->getMessage());
        } finally {
            rmdir($blocked);
        }

        $root = sys_get_temp_dir() . '/tgbotphp-root-' . bin2hex(random_bytes(2));
        mkdir($root);
        $missing = $root . '/missing-' . bin2hex(random_bytes(2));
        $localTransport = new FakeTransport();
        $local = new ApiClient(new Config(
            Updates::TOKEN,
            apiBaseUrl: 'http://127.0.0.1:9',
            enforceHttps: false,
            localFileRoot: $root,
            retry: RetryPolicy::none(),
        ), $localTransport);
        $localTransport->queueResult(['file_id' => 'f', 'file_path' => $missing]);

        try {
            $local->downloadFile('f');
            self::fail('Read a missing local file');
        } catch (\InvalidArgumentException) {
            // The path is not a local file, and an absolute path is not a URL either.
        }

        $source = $root . '/note.txt';
        file_put_contents($source, 'local');
        $directory = $root . '/dest';
        mkdir($directory);
        $localTransport->queueResult(['file_id' => 'f', 'file_path' => $source]);
        try {
            $local->downloadFile('f', $directory);
            self::fail('Copied a local file onto a directory');
        } catch (StorageException $e) {
            self::assertStringContainsString('Unable to write file', $e->getMessage());
        }

        $huge = $root . '/huge.bin';
        $handle = fopen($huge, 'wb');
        self::assertIsResource($handle);
        ftruncate($handle, ApiClient::MAX_MEMORY_DOWNLOAD + 1);
        fclose($handle);
        $localTransport->queueResult(['file_id' => 'f', 'file_path' => $huge]);
        try {
            $local->downloadFile('f');
            self::fail('Loaded a huge local file into memory');
        } catch (StorageException $e) {
            self::assertStringContainsString('in-memory', $e->getMessage());
        }

        $hidden = $root . '/hidden.txt';
        file_put_contents($hidden, 'secret');
        chmod($hidden, 0000);
        $localTransport->queueResult(['file_id' => 'f', 'file_path' => $hidden]);
        try {
            $local->downloadFile('f');
            self::fail('Read an unreadable local file');
        } catch (StorageException $e) {
            self::assertStringContainsString('Unable to read file', $e->getMessage());
        } finally {
            chmod($hidden, 0600);
        }

        $outside = new ReflectionMethod(ApiClient::class, 'copyLocalFile');
        try {
            $outside->invoke($local, '/etc/hosts', null);
            self::fail('Copied a file outside the root');
        } catch (StorageException $e) {
            self::assertStringContainsString('outside the local file root', $e->getMessage());
        } finally {
            @unlink($huge);
            @unlink($hidden);
            @unlink($source);
            rmdir($directory);
            rmdir($root);
        }
    }
}
