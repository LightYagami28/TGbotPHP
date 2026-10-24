<?php

/**
 * Webhook bot
 *
 * 1. Generate a secret: export TELEGRAM_SECRET_TOKEN=$(openssl rand -hex 32)
 * 2. Serve this file over HTTPS (e.g. https://example.com/webhook.php)
 * 3. Register the same secret:
 *    TELEGRAM_BOT_TOKEN=... vendor/bin/tgbot webhook:set --url=https://example.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
 *
 * handle() answers 403 when TELEGRAM_SECRET_TOKEN is missing or the request
 * header does not match it. The secret is 1-256 characters: A-Z a-z 0-9 _ -
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TGbotPHP\Cache\FileCache;
use TGbotPHP\Core\Config;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Rate\RateLimiter;
use TGbotPHP\Support\Value;
use TGbotPHP\Utilities\Formatter;
use TGbotPHP\Utilities\Keyboard;

$secret = Value::env('TELEGRAM_SECRET_TOKEN');
if ($secret === null) {
    throw new RuntimeException('Set TELEGRAM_SECRET_TOKEN before serving this webhook. Generate one with: openssl rand -hex 32');
}

$config = new Config(
    token: Value::env('TELEGRAM_BOT_TOKEN') ?? '',
    secretToken: $secret,
);

// Webhook requests run in separate PHP processes: state must live in a persistent cache
$cache = new FileCache(sys_get_temp_dir() . '/tgbotphp-cache');

$bot = new Bot($config);
$bot->setUsername(Value::env('TELEGRAM_BOT_USERNAME'));
$bot->useConversations($cache);

// At most 20 updates per user per minute
$bot->middleware((new RateLimiter($cache))->middleware(20, 60));

$bot->command('start', function (stdClass $message, Bot $bot): void {
    $bot->reply($message, 'Hi ' . Formatter::bold(Value::string(Value::path($message, 'from', 'first_name'), 'there')) . '! What should I call you?');
    $bot->setState($message, 'ask_name');
});

$bot->state('ask_name', function (stdClass $message, Bot $bot): void {
    $bot->clearState($message);
    $bot->reply($message, 'Nice to meet you, ' . Formatter::escape(Value::string($message->text ?? null)) . '!', [
        'reply_markup' => Keyboard::inline(['👍 Like' => 'like', '👎 Dislike' => 'dislike']),
    ]);
});

$bot->callback('*', function (stdClass $callback, Bot $bot, array $matches): void {
    $bot->answer($callback, $matches[0] === 'like' ? 'Thanks!' : 'Noted');
});

$bot->onError(function (Throwable $e): void {
    error_log('Bot error: ' . $e->getMessage());
});

$bot->handle();
