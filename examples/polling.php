<?php

/**
 * Long polling bot: php examples/polling.php
 *
 * No web server needed. Make sure no webhook is set (vendor/bin/tgbot webhook:delete).
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TGbotPHP\Framework\Bot;
use TGbotPHP\Support\Value;
use TGbotPHP\Utilities\Formatter;
use TGbotPHP\Utilities\InlineKeyboard;
use TGbotPHP\Utilities\Keyboard;

$bot = new Bot(Value::env('TELEGRAM_BOT_TOKEN') ?? '');

$bot->setMyCommands([
    'start' => 'Start the bot',
    'menu' => 'Show the menu',
    'dice' => 'Roll a dice',
]);

$bot->command('start', function (stdClass $message, Bot $bot, string $payload): void {
    $text = 'Welcome to ' . Formatter::bold('TGbotPHP') . '!';

    if ($payload !== '') {
        $text .= "\nDeep link payload: " . Formatter::code($payload);
    }

    $bot->reply($message, $text);
});

$bot->command('menu', function (stdClass $message, Bot $bot): void {
    $keyboard = InlineKeyboard::make()
        ->button('Page 1', 'page:1')->button('Page 2', 'page:2')
        ->row()
        ->url('Bot API docs', 'https://core.telegram.org/bots/api');

    $bot->reply($message, 'Choose a page:', ['reply_markup' => $keyboard]);
});

$bot->callback('page:*', function (stdClass $callback, Bot $bot, array $matches): void {
    $page = Value::int($matches[1] ?? null, 1);
    $bot->answer($callback);
    $bot->edit($callback, "You are on page $page", [
        'reply_markup' => Keyboard::pagination($page, 5),
    ]);
});

$bot->command('dice', fn(stdClass $message, Bot $bot) => $bot->sendDice($bot->chatId($message)));

$bot->hears('/^(hi|hello|ciao)\b/i', fn(stdClass $message, Bot $bot) => $bot->reply($message, '👋'));

$bot->fallback(fn(stdClass $message, Bot $bot) => $bot->reply($message, Formatter::escape(Value::string($message->text ?? null))));

$bot->onError(function (Throwable $e): void {
    fwrite(STDERR, '[error] ' . $e->getMessage() . PHP_EOL);
});

// Stop cleanly on Ctrl+C when the pcntl extension is available
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, fn() => $bot->stop());
    pcntl_signal(SIGTERM, fn() => $bot->stop());
}

echo "Polling... press Ctrl+C to stop\n";
$bot->poll();
