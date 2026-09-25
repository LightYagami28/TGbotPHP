<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use CURLStringFile;
use PHPUnit\Framework\TestCase;
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Core\Config;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Exceptions\TooManyRequestsException;
use TGbotPHP\Tests\Support\FakeTransport;
use TGbotPHP\Tests\Support\Updates;
use TGbotPHP\Types\InputFile;
use TGbotPHP\Utilities\Keyboard;

final class ApiClientTest extends TestCase
{
    private FakeTransport $transport;
    private ApiClient $client;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->client = new ApiClient(new Config(Updates::TOKEN, maxRetries: 2, maxRetryDelay: 0), $this->transport);
    }

    public function testBuildsMethodUrl(): void
    {
        $this->transport->queueResult(['id' => 1, 'is_bot' => true, 'first_name' => 'Bot', 'username' => 'test_bot']);

        $me = $this->client->getMe();

        self::assertSame('test_bot', $me['username']);
        self::assertSame('https://api.telegram.org/bot' . Updates::TOKEN . '/getMe', $this->transport->lastRequest()['url']);
    }

    public function testCustomApiServer(): void
    {
        $client = new ApiClient(
            new Config(Updates::TOKEN, enforceHttps: false, apiBaseUrl: 'http://localhost:8081/'),
            $this->transport
        );

        $client->getMe();

        self::assertSame('http://localhost:8081/bot' . Updates::TOKEN . '/getMe', $this->transport->lastRequest()['url']);
    }

    public function testRejectsPlainHttpApiServerByDefault(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Config(Updates::TOKEN, apiBaseUrl: 'http://localhost:8081');
    }

    public function testRejectsMalformedToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Config('12345/../../evil:token');
    }

    public function testPreparesFields(): void
    {
        $this->transport->queueResult(['message_id' => 10]);

        $this->client->sendMessage(42, 'Hello', replyMarkup: Keyboard::inline(['Yes' => 'y']), disableWebPagePreview: true);

        $request = $this->transport->lastRequest();
        self::assertSame('sendMessage', $request['method']);
        self::assertFalse($request['multipart']);
        self::assertSame('42', $request['fields']['chat_id']);
        self::assertSame('HTML', $request['fields']['parse_mode']);
        self::assertSame('{"is_disabled":true}', $request['fields']['link_preview_options']);
        self::assertSame('{"inline_keyboard":[[{"text":"Yes","callback_data":"y"}]]}', $request['fields']['reply_markup']);
        self::assertArrayNotHasKey('disable_notification', $request['fields']);
    }

    public function testOptionsAreMergedAndOverrideDefaults(): void
    {
        $this->client->sendMessage(42, 'Hi', options: ['message_thread_id' => 3, 'parse_mode' => 'MarkdownV2', 'protect_content' => true]);

        $fields = $this->transport->lastRequest()['fields'];
        self::assertSame('3', $fields['message_thread_id']);
        self::assertSame('MarkdownV2', $fields['parse_mode']);
        self::assertSame('true', $fields['protect_content']);
    }

    public function testBooleanMethodsReturnBool(): void
    {
        $this->transport->queueResult(true);

        self::assertTrue($this->client->deleteMessage(42, 1));
        self::assertTrue($this->client->answerCallbackQuery('abc', 'Done'));
    }

    public function testIntegerResult(): void
    {
        $this->transport->queueResult(17);

        self::assertSame(17, $this->client->getChatMemberCount(-100123));
    }

    public function testFileIdIsSentAsString(): void
    {
        $this->client->sendPhoto(42, 'AgACAgIAAxkBAAIB', 'caption');

        $request = $this->transport->lastRequest();
        self::assertFalse($request['multipart']);
        self::assertSame('AgACAgIAAxkBAAIB', $request['fields']['photo']);
    }

    public function testInputFileIsUploadedAsMultipart(): void
    {
        $this->client->sendDocument(42, InputFile::fromContents('a,b', 'report.csv', 'text/csv'));

        $request = $this->transport->lastRequest();
        self::assertTrue($request['multipart']);
        self::assertInstanceOf(CURLStringFile::class, $request['fields']['document']);
        self::assertSame('report.csv', $request['fields']['document']->postname);
        self::assertArrayNotHasKey('parse_mode', $request['fields']);
    }

    public function testNestedInputFilesBecomeAttachments(): void
    {
        $this->transport->queueResult([]);

        $this->client->sendMediaGroup(42, [
            ['type' => 'photo', 'media' => InputFile::fromContents('png', 'a.png')],
            ['type' => 'photo', 'media' => 'https://example.com/b.png'],
        ]);

        $request = $this->transport->lastRequest();
        self::assertTrue($request['multipart']);
        self::assertSame(
            '[{"type":"photo","media":"attach://file0"},{"type":"photo","media":"https://example.com/b.png"}]',
            $request['fields']['media']
        );
        self::assertInstanceOf(CURLStringFile::class, $request['fields']['file0']);
    }

    public function testInputFileFromMissingPathThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        InputFile::fromPath('/does/not/exist.png');
    }

    public function testApiErrorKeepsTelegramDescription(): void
    {
        $this->transport->queueError(400, 'Bad Request: chat not found');

        try {
            $this->client->sendMessage(1, 'x');
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame('Bad Request: chat not found', $e->getMessage());
            self::assertSame(400, $e->getCode());
            self::assertSame('sendMessage', $e->getApiMethod());
        }
    }

    public function testMigrateToChatId(): void
    {
        $this->transport->queueError(400, 'Bad Request: group chat was upgraded', ['migrate_to_chat_id' => -1001234]);

        try {
            $this->client->sendMessage(1, 'x');
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(-1001234, $e->getMigrateToChatId());
        }
    }

    public function testRetriesAfterFloodControl(): void
    {
        $this->transport
            ->queueError(429, 'Too Many Requests: retry after 0', ['retry_after' => 0])
            ->queueResult(['message_id' => 3]);

        $result = $this->client->sendMessage(1, 'x');

        self::assertSame(3, $result['message_id']);
        self::assertCount(2, $this->transport->requests);
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->transport->queueError(429, 'Too Many Requests', ['retry_after' => 0]);
        }

        try {
            $this->client->sendMessage(1, 'x');
            self::fail('Expected TooManyRequestsException');
        } catch (TooManyRequestsException $e) {
            self::assertSame(0, $e->getRetryAfter());
            self::assertCount(3, $this->transport->requests);
        }
    }

    public function testDoesNotWaitLongerThanMaxRetryDelay(): void
    {
        $this->transport->queueError(429, 'Too Many Requests', ['retry_after' => 60]);

        $this->expectException(TooManyRequestsException::class);

        $this->client->sendMessage(1, 'x');
    }

    public function testInvalidJsonResponse(): void
    {
        $this->transport->queueRaw('<html>502 Bad Gateway</html>', 502);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(502);

        $this->client->getMe();
    }

    public function testCallGenericMethod(): void
    {
        $this->transport->queueResult(['ok' => 1]);

        $result = $this->client->call('someFutureMethod', ['chat_id' => 1, 'flag' => false]);

        self::assertSame(['ok' => 1], $result);
        self::assertSame('someFutureMethod', $this->transport->lastRequest()['method']);
        self::assertSame('false', $this->transport->lastRequest()['fields']['flag']);
    }

    public function testLongPollingTimeoutExtendsHttpTimeout(): void
    {
        $this->transport->queueResult([]);

        $this->client->getUpdates(timeout: 30);

        self::assertSame(40, $this->transport->lastRequest()['timeout']);
    }

    public function testSetMyCommandsAcceptsMapAndScopeType(): void
    {
        $this->client->setMyCommands(['/start' => 'Start the bot', 'help' => 'Help'], 'all_private_chats');

        $fields = $this->transport->lastRequest()['fields'];
        self::assertSame(
            '[{"command":"start","description":"Start the bot"},{"command":"help","description":"Help"}]',
            $fields['commands']
        );
        self::assertSame('{"type":"all_private_chats"}', $fields['scope']);
    }

    public function testReactionShortcut(): void
    {
        $this->client->setMessageReaction(1, 2, ['👍']);

        self::assertSame('[{"type":"emoji","emoji":"👍"}]', $this->transport->lastRequest()['fields']['reaction']);
    }

    public function testDeprecatedAliasesUseCurrentMethods(): void
    {
        $this->client->kickChatMember(1, 2);
        $this->client->pinMessage(1, 2);
        $this->client->unpinMessage(1);

        self::assertSame(['banChatMember', 'pinChatMessage', 'unpinChatMessage'], $this->transport->methods());
    }

    public function testSetWebhookRequiresHttps(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->client->setWebhook('http://example.com/hook');
    }

    public function testDownloadFile(): void
    {
        $this->transport->queueResult(['file_id' => 'f', 'file_path' => 'photos/file_1.jpg']);
        $this->transport->queueRaw('binary-data');

        self::assertSame('binary-data', $this->client->downloadFile('f'));
        self::assertSame(
            'https://api.telegram.org/file/bot' . Updates::TOKEN . '/photos/file_1.jpg',
            $this->transport->lastRequest()['url']
        );
    }
}
