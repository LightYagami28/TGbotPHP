<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TGbotPHP\CLI\Console;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Testing\FakeTransport;
use TGbotPHP\Tests\Support\Updates;

final class ConsoleTest extends TestCase
{
    private FakeTransport $transport;

    /** @var resource */
    private $output;

    /** @var resource */
    private $errors;

    private Console $console;

    #[\Override]
    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $output = fopen('php://memory', 'w+');
        self::assertIsResource($output);
        $this->output = $output;
        $errors = fopen('php://memory', 'w+');
        self::assertIsResource($errors);
        $this->errors = $errors;
        $this->console = new Console(fn(string $token) => new Bot($token, transport: $this->transport), $this->output, $this->errors);
    }

    private function readOutput(): string
    {
        rewind($this->output);
        return (string) stream_get_contents($this->output);
    }

    private function readErrors(): string
    {
        rewind($this->errors);
        return (string) stream_get_contents($this->errors);
    }

    /**
     * @param string ...$arguments
     */
    private function runWithToken(string ...$arguments): int
    {
        return $this->console->run(['tgbot', ...$arguments, '--token=' . Updates::TOKEN]);
    }

    public function testVersion(): void
    {
        self::assertSame(0, $this->console->run(['tgbot', 'version']));
        self::assertStringContainsString('TGbotPHP v3.0.0', $this->readOutput());
    }

    public function testBotInfo(): void
    {
        $this->transport->queueResult(['id' => 1, 'is_bot' => true, 'first_name' => 'Test', 'username' => 'test_bot']);

        self::assertSame(0, $this->console->run(['tgbot', 'bot:info', '--token=' . Updates::TOKEN]));
        self::assertStringContainsString('@test_bot', $this->readOutput());
    }

    public function testSetWebhookWithSecret(): void
    {
        $this->transport->queueResult(true);

        $code = $this->console->run([
            'tgbot', 'webhook:set', '--token=' . Updates::TOKEN, '--url=https://example.com/hook', '--secret=webhook-secret-value', '--drop-pending',
        ]);

        self::assertSame(0, $code);
        $fields = $this->transport->lastRequest()['fields'];
        self::assertSame('https://example.com/hook', $fields['url']);
        self::assertSame('webhook-secret-value', $fields['secret_token']);
        self::assertSame('true', $fields['drop_pending_updates']);
    }

    public function testErrorsReturnNonZeroExitCode(): void
    {
        $this->transport->queueError(401, 'Unauthorized');

        self::assertSame(1, $this->console->run(['tgbot', 'bot:info', '--token=' . Updates::TOKEN]));
        self::assertStringContainsString('Error: Unauthorized', $this->readErrors());
        self::assertSame('', $this->readOutput());
    }

    public function testMissingToken(): void
    {
        $previous = getenv('TELEGRAM_BOT_TOKEN');
        putenv('TELEGRAM_BOT_TOKEN');

        try {
            self::assertSame(1, $this->console->run(['tgbot', 'bot:info']));
            self::assertStringContainsString('TELEGRAM_BOT_TOKEN is required', $this->readErrors());
        } finally {
            if ($previous !== false) {
                putenv("TELEGRAM_BOT_TOKEN=$previous");
            }
        }
    }

    public function testUnknownCommand(): void
    {
        self::assertSame(1, $this->console->run(['tgbot', 'nope']));
        self::assertStringContainsString('Unknown command: nope', $this->readErrors());
    }

    public function testTokenFromEnvironment(): void
    {
        $previous = getenv('TELEGRAM_BOT_TOKEN');
        putenv('TELEGRAM_BOT_TOKEN=' . Updates::TOKEN);
        $this->transport->queueResult(['id' => 1, 'is_bot' => true, 'first_name' => 'Env', 'username' => 'env_bot']);

        try {
            self::assertSame(0, $this->console->run(['tgbot', 'bot:info']));
            self::assertStringContainsString('@env_bot', $this->readOutput());
        } finally {
            putenv($previous === false ? 'TELEGRAM_BOT_TOKEN' : "TELEGRAM_BOT_TOKEN=$previous");
        }
    }

    public function testHelp(): void
    {
        self::assertSame(0, $this->console->run(['tgbot']));
        self::assertStringContainsString('webhook:set', $this->readOutput());
    }

    public function testWebhookInfo(): void
    {
        $this->transport->queueResult([
            'url' => 'https://example.com/hook',
            'pending_update_count' => 3,
            'max_connections' => 40,
            'last_error_date' => 1_700_000_000,
            'last_error_message' => 'Connection timed out',
        ]);

        self::assertSame(0, $this->runWithToken('webhook:info'));

        $output = $this->readOutput();
        self::assertStringContainsString('URL: https://example.com/hook', $output);
        self::assertStringContainsString('Pending updates: 3', $output);
        self::assertStringContainsString('Max connections: 40', $output);
        self::assertStringContainsString('Connection timed out', $output);
    }

    public function testWebhookInfoWithoutWebhook(): void
    {
        $this->transport->queueResult(['url' => '', 'pending_update_count' => 0]);

        self::assertSame(0, $this->runWithToken('webhook:info'));
        self::assertStringContainsString('URL: ', $this->readOutput());
        self::assertStringNotContainsString('Last error', $this->readOutput());
    }

    public function testSetWebhookRequiresUrl(): void
    {
        self::assertSame(1, $this->runWithToken('webhook:set'));
        self::assertStringContainsString('--url is required', $this->readErrors());
        self::assertSame([], $this->transport->requests);
    }

    public function testDeleteWebhook(): void
    {
        self::assertSame(0, $this->runWithToken('webhook:delete', '--drop-pending'));

        self::assertSame('deleteWebhook', $this->transport->lastRequest()['method']);
        self::assertSame('true', $this->transport->lastRequest()['fields']['drop_pending_updates']);
        self::assertStringContainsString('Webhook deleted', $this->readOutput());
    }

    public function testListCommands(): void
    {
        $this->transport->queueResult([['command' => 'start', 'description' => 'Start the bot']]);

        self::assertSame(0, $this->runWithToken('commands:list', '--scope=all_private_chats', '--lang=it'));

        self::assertStringContainsString('/start - Start the bot', $this->readOutput());
        $fields = $this->transport->lastRequest()['fields'];
        self::assertSame('{"type":"all_private_chats"}', $fields['scope']);
        self::assertSame('it', $fields['language_code']);
    }

    public function testListCommandsWhenNoneAreSet(): void
    {
        $this->transport->queueResult([]);

        self::assertSame(0, $this->runWithToken('commands:list'));
        self::assertStringContainsString('No commands set', $this->readOutput());
    }

    public function testDeleteCommands(): void
    {
        self::assertSame(0, $this->runWithToken('commands:delete'));

        self::assertSame('deleteMyCommands', $this->transport->lastRequest()['method']);
        self::assertStringContainsString('Commands deleted', $this->readOutput());
    }
}
