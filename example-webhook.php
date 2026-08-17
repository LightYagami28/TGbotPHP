<?php

declare(strict_types=1);

namespace TGbotPHP\Examples;

use TGbotPHP\Framework\Bot;
use TGbotPHP\Exceptions\ApiException;

require_once 'vendor/autoload.php';

define('BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: die('❌ TELEGRAM_BOT_TOKEN not set'));

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// HTTPS enforcement
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403);
    exit(json_encode(['error' => 'HTTPS required']));
}

// IP validation
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$allowedRanges = [
    '149.154.160.0/20',
    '91.108.4.0/22',
    '91.108.56.0/21',
    '45.142.120.0/22',
];

$isAllowed = false;
foreach ($allowedRanges as $range) {
    if (ipInRange($clientIp, $range)) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

try {
    // Initialize bot
    $bot = new Bot(
        token: BOT_TOKEN,
        debug: getenv('DEBUG_MODE') === 'true',
        debugFile: getenv('DEBUG_LOG_FILE') ?: false
    );

    // Get webhook JSON
    $webhookJson = file_get_contents('php://input');

    if (empty($webhookJson)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Empty request']));
    }

    // Handle update
    $bot->handleUpdate($webhookJson);

    // Register handlers
    $bot->command('/start', function ($update) use ($bot) {
        $chatId = $update->message->chat->id;
        $firstName = $update->message->from->first_name ?? 'User';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📋 Help', 'callback_data' => 'help'],
                    ['text' => '⚙️ Settings', 'callback_data' => 'settings'],
                ],
                [
                    ['text' => '👤 Profile', 'callback_data' => 'profile'],
                ],
            ],
        ];

        $bot->sendMessage(
            chatId: $chatId,
            text: "Welcome <b>$firstName</b>! 👋\n\nUse the buttons below to navigate.",
            parseMode: 'HTML',
            replyMarkup: $keyboard
        );
    });

    $bot->command('/help', function ($update) use ($bot) {
        $chatId = $update->message->chat->id;

        $bot->sendMessage(
            chatId: $chatId,
            text: "<b>📖 Available Commands</b>\n\n" .
                  "/start - Start the bot\n" .
                  "/help - Show this message\n" .
                  "/me - Get bot info",
            parseMode: 'HTML'
        );
    });

    $bot->command('/me', function ($update) use ($bot) {
        $chatId = $update->message->chat->id;

        try {
            $me = $bot->getMe();
            if ($me) {
                $bot->sendMessage(
                    chatId: $chatId,
                    text: "<b>🤖 Bot Info</b>\n\n" .
                          "ID: <code>{$me['id']}</code>\n" .
                          "Username: @{$me['username']}\n" .
                          "Name: {$me['first_name']}",
                    parseMode: 'HTML'
                );
            }
        } catch (ApiException $e) {
            $bot->sendMessage(
                chatId: $chatId,
                text: "❌ Error: " . $e->getMessage()
            );
        }
    });

    $bot->callback('help', function ($update) use ($bot) {
        $chatId = $update->callback_query->message->chat->id;
        $messageId = $update->callback_query->message->message_id;

        $bot->editMessageText(
            chatId: $chatId,
            messageId: $messageId,
            text: "❓ <b>Help</b>\n\nThis is the help menu.\n\nUse /help command for more information.",
            parseMode: 'HTML'
        );
    });

    $bot->callback('settings', function ($update) use ($bot) {
        $chatId = $update->callback_query->message->chat->id;
        $messageId = $update->callback_query->message->message_id;

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌍 Language', 'callback_data' => 'lang'],
                    ['text' => '🔔 Notifications', 'callback_data' => 'notif'],
                ],
                [
                    ['text' => '← Back', 'callback_data' => 'back'],
                ],
            ],
        ];

        $bot->editMessageText(
            chatId: $chatId,
            messageId: $messageId,
            text: "⚙️ <b>Settings</b>",
            parseMode: 'HTML',
            replyMarkup: $keyboard
        );
    });

    $bot->callback('profile', function ($update) use ($bot) {
        $chatId = $update->callback_query->message->chat->id;
        $userId = $update->callback_query->from->id;
        $firstName = $update->callback_query->from->first_name ?? 'User';

        $bot->sendMessage(
            chatId: $chatId,
            text: "👤 <b>Profile</b>\n\n" .
                  "User ID: <code>$userId</code>\n" .
                  "Name: <b>$firstName</b>",
            parseMode: 'HTML'
        );
    });

    $bot->callback('back', function ($update) use ($bot) {
        $chatId = $update->callback_query->message->chat->id;
        $messageId = $update->callback_query->message->message_id;
        $firstName = $update->callback_query->from->first_name ?? 'User';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📋 Help', 'callback_data' => 'help'],
                    ['text' => '⚙️ Settings', 'callback_data' => 'settings'],
                ],
                [
                    ['text' => '👤 Profile', 'callback_data' => 'profile'],
                ],
            ],
        ];

        $bot->editMessageText(
            chatId: $chatId,
            messageId: $messageId,
            text: "Welcome <b>$firstName</b>! 👋\n\nUse the buttons below to navigate.",
            parseMode: 'HTML',
            replyMarkup: $keyboard
        );
    });

    http_response_code(200);
    echo json_encode(['ok' => true]);
} catch (ApiException $e) {
    http_response_code(500);
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal error']);
} catch (\Exception $e) {
    http_response_code(500);
    error_log("Error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal error']);
}

/**
 * Check if IP is in CIDR range
 */
function ipInRange(string $ip, string $range): bool
{
    [$subnet, $bits] = explode('/', $range);
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - (int)$bits);
    $subnet &= $mask;

    return ($ip & $mask) === $subnet;
}
