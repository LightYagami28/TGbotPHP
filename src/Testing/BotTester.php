<?php

declare(strict_types=1);

namespace TGbotPHP\Testing;

use stdClass;
use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;

/**
 * A bot wired to a FakeTransport, to test handlers without network access
 *
 *     $tester = new BotTester();
 *     $tester->bot->command('start', fn($message, Bot $bot) => $bot->reply($message, 'Hello'));
 *
 *     $tester->receive(FakeUpdate::message('/start'));
 *
 *     assert($tester->lastSent('sendMessage')['text'] === 'Hello');
 *
 * Works with any test framework: it only records what the bot sent.
 */
final class BotTester
{
    /** Well-formed token that is not a real bot's */
    public const string TOKEN = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';

    public readonly Bot $bot;

    public readonly FakeTransport $transport;

    public function __construct(?Config $config = null, string $username = 'test_bot')
    {
        $this->transport = new FakeTransport();
        $this->bot = new Bot($config ?? new Config(self::TOKEN), $this->transport);
        $this->bot->setUsername($username);
    }

    /**
     * Process an update, as if Telegram had sent it
     *
     * @param array<string, mixed>|stdClass|string $update An array from FakeUpdate, a decoded update or JSON
     */
    public function receive(array|stdClass|string $update): self
    {
        $this->bot->handleUpdate($update);

        return $this;
    }

    /**
     * Parameters of every request sent, or of the requests to one method
     *
     * Values are encoded as Telegram receives them: numbers and booleans are
     * strings, arrays are JSON.
     *
     * @return list<array<string, mixed>>
     */
    public function sent(?string $method = null): array
    {
        $requests = array_filter(
            $this->transport->requests,
            static fn(array $request): bool => $method === null || $request['method'] === $method,
        );

        return array_column($requests, 'fields');
    }

    /**
     * Parameters of the last request sent (to a method), or null
     *
     * @return array<string, mixed>|null
     */
    public function lastSent(?string $method = null): ?array
    {
        $sent = $this->sent($method);

        return $sent === [] ? null : $sent[array_key_last($sent)];
    }

    /**
     * Names of the methods called, in order
     *
     * @return list<string>
     */
    public function methods(): array
    {
        return array_column($this->transport->requests, 'method');
    }

    /**
     * Forget the requests sent so far
     */
    public function reset(): self
    {
        $this->transport->reset();

        return $this;
    }
}
