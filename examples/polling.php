<?php

/**
 * Long polling bot: php examples/polling.php
 *
 * No web server needed. Make sure no webhook is set (vendor/bin/tgbot webhook:delete).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TGbotPHP\Framework\Bot;
use TGbotPHP\Utilities\Formatter;
use TGbotPHP\Utilities\InlineKeyboard;
use TGbotPHP\Utilities\Keyboard;

$bot = new Bot((string) getenv('TELEGRAM_BOT_TOKEN'));

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
    $page = (int) $matches[1];
    $bot->answer($callback);
    $bot->editMessageText(
        $callback->message->chat->id,
        $callback->message->message_id,
        "You are on page $page",
        replyMarkup: Keyboard::pagination($page, 5)
    );
});

$bot->command('dice', fn(stdClass $message, Bot $bot) => $bot->sendDice($message->chat->id));

$bot->hears('/^(hi|hello|ciao)\b/i', fn(stdClass $message, Bot $bot) => $bot->reply($message, '👋'));

$bot->fallback(fn(stdClass $message, Bot $bot) => $bot->reply($message, Formatter::escape($message->text)));

$bot->onError(function (Throwable $e, ?stdClass $update): void {
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
