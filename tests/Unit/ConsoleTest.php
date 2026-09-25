<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TGbotPHP\CLI\Console;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Tests\Support\FakeTransport;
use TGbotPHP\Tests\Support\Updates;

final class ConsoleTest extends TestCase
{
    private FakeTransport $transport;

    /** @var resource */
    private $output;

    private Console $console;

    #[\Override]
    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $output = fopen('php://memory', 'w+');
        self::assertIsResource($output);
        $this->output = $output;
        $this->console = new Console(fn(string $token) => new Bot($token, transport: $this->transport), $this->output);
    }

    private function readOutput(): string
    {
        rewind($this->output);
        return (string) stream_get_contents($this->output);
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
        self::assertStringContainsString('Unauthorized', $this->readOutput());
    }

    public function testMissingToken(): void
    {
        $previous = getenv('TELEGRAM_BOT_TOKEN');
        putenv('TELEGRAM_BOT_TOKEN');

        try {
            self::assertSame(1, $this->console->run(['tgbot', 'bot:info']));
            self::assertStringContainsString('TELEGRAM_BOT_TOKEN is required', $this->readOutput());
        } finally {
            if ($previous !== false) {
                putenv("TELEGRAM_BOT_TOKEN=$previous");
            }
        }
    }

    public function testUnknownCommand(): void
    {
        self::assertSame(1, $this->console->run(['tgbot', 'nope']));
    }
}
