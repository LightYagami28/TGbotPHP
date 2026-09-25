<?php

/**
 * Webhook bot
 *
 * 1. Serve this file over HTTPS (e.g. https://example.com/webhook.php)
 * 2. Register it:
 *    TELEGRAM_BOT_TOKEN=... vendor/bin/tgbot webhook:set --url=https://example.com/webhook.php --secret=$TELEGRAM_SECRET_TOKEN
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

$config = new Config(
    token: Value::env('TELEGRAM_BOT_TOKEN') ?? '',
    secretToken: Value::env('TELEGRAM_SECRET_TOKEN') ?? false,
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
