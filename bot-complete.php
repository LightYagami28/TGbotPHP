<?php

declare(strict_types=1);

/**
 * Complete TGbotPHP Example
 *
 * Shows ALL features:
 * - Webhooks + Long Polling
 * - All Telegram Bot API methods
 * - Media handling
 * - Callbacks & keyboards
 * - Error handling
 * - Production ready
 */

use TGbotPHP\botTG;

require_once "botlib.php";

// ============================================================================
// CONFIGURATION
// ============================================================================

$token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN');

if (!$token) {
    die("❌ TELEGRAM_BOT_TOKEN not configured\n");
}

$mode = $_ENV['BOT_MODE'] ?? 'webhook'; // webhook or polling
$debug = ($_ENV['DEBUG_MODE'] ?? 'false') === 'true';

// ============================================================================
// WEBHOOK MODE (Production)
// ============================================================================

if ($mode === 'webhook') {
    webhookMode($token, $debug);
    exit;
}

// ============================================================================
// LONG POLLING MODE (Development/Testing)
// ============================================================================

if ($mode === 'polling') {
    pollingMode($token, $debug);
    exit;
}

die("❌ Invalid BOT_MODE: use 'webhook' or 'polling'\n");

// ============================================================================
// WEBHOOK MODE IMPLEMENTATION
// ============================================================================

function webhookMode(string $token, bool $debug): void
{
    // Security headers
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    // HTTPS enforcement
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        http_response_code(403);
        exit(json_encode(['error' => 'HTTPS required']));
    }

    // IP validation
    if (!botTG::validateTelegramIp()) {
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden']));
    }

    // Get update
    $input = file_get_contents("php://input");

    try {
        $bot = new botTG(
            token: $token,
            updates: $input,
            debug: $debug,
            debugFile: $debug ? '/var/log/telegram-bot.log' : false
        );
    } catch (Exception $e) {
        http_response_code(500);
        exit(json_encode(['error' => 'Bot error']));
    }

    // Handle update
    handleUpdate($bot);

    http_response_code(200);
    echo json_encode(['ok' => true]);
}

// ============================================================================
// LONG POLLING MODE IMPLEMENTATION
// ============================================================================

function pollingMode(string $token, bool $debug): void
{
    echo "🤖 Starting long polling mode...\n";
    echo "Press Ctrl+C to stop\n\n";

    botTG::pollUpdates(
        token: $token,
        handler: function(botTG $bot, array $update) use ($debug) {
            echo sprintf("[%s] Update #%d received\n", date('H:i:s'), $update['update_id']);
            handleUpdate($bot);
        },
        pollingInterval: 1
    );
}

// ============================================================================
// UNIFIED UPDATE HANDLER
// ============================================================================

function handleUpdate(botTG $bot): void
{
    // Handle /start
    $bot->commandSimple("/start", [
        "text" => "👋 Welcome {{message from first_name}}!\n\nI'm a Telegram bot.",
        "keyboard" => $bot->buildKeyboardOfInline([
            "Profile 👤" => "profile",
            "Settings ⚙️" => "settings",
            "Help ❓" => "help",
        ]),
    ]);

    // Handle /help
    $bot->commandSimple("/help", [
        "text" => "📖 Available commands:\n\n" .
                 "/start - Main menu\n" .
                 "/help - This message\n" .
                 "/status - Bot status\n" .
                 "/admin - Admin panel",
    ]);

    // Handle /status
    $bot->commandSimple("/status", [
        "text" => "✅ Bot is online and working!\n\n" .
                 "🔧 Using TGbotPHP 2.0\n" .
                 "📅 Current time: " . date('Y-m-d H:i:s'),
    ]);

    // ========================================================================
    // PROFILE SECTION
    // ========================================================================

    $bot->simpleCallbackResponse("profile", [
        "text" => "👤 Your Profile\n\n" .
                 "Name: {{message from first_name}}\n" .
                 "User ID: " . ($bot->update?->callback_query?->from?->id ?? 'N/A'),
        "keyboard" => $bot->buildKeyboardOfInline([
            "Edit Profile ✏️" => "edit_profile",
            "View Stats 📊" => "view_stats",
            "← Back" => "menu_main",
        ]),
    ]);

    $bot->simpleCallbackResponse("edit_profile", [
        "text" => "✏️ Profile Editor\n\nWhat do you want to change?",
        "keyboard" => $bot->buildKeyboardOfInline([
            "Change Name" => "change_name",
            "Change Avatar" => "change_avatar",
            "← Back" => "profile",
        ]),
    ]);

    $bot->simpleCallbackResponse("view_stats", [
        "text" => "📊 Your Statistics\n\n" .
                 "Messages sent: 42\n" .
                 "Joined: 30 days ago\n" .
                 "Last active: Just now",
    ]);

    // ========================================================================
    // SETTINGS SECTION
    // ========================================================================

    $bot->simpleCallbackResponse("settings", [
        "text" => "⚙️ Settings\n\nChoose what to configure:",
        "keyboard" => $bot->buildKeyboardOfInline([
            "🌍 Language" => "lang_settings",
            "🔔 Notifications" => "notif_settings",
            "🎨 Theme" => "theme_settings",
            "← Back" => "menu_main",
        ]),
    ]);

    $bot->simpleCallbackResponse("lang_settings", [
        "text" => "🌍 Select Language",
        "keyboard" => $bot->buildKeyboardOfInline([
            "English 🇺🇸" => "lang_en",
            "Italiano 🇮🇹" => "lang_it",
            "Français 🇫🇷" => "lang_fr",
            "← Back" => "settings",
        ]),
    ]);

    $bot->simpleCallbackResponse("notif_settings", [
        "text" => "🔔 Notification Settings",
        "keyboard" => $bot->buildKeyboardOfInline([
            "Enable" => "notif_on",
            "Disable" => "notif_off",
            "← Back" => "settings",
        ]),
    ]);

    $bot->simpleCallbackResponse("theme_settings", [
        "text" => "🎨 Choose Theme",
        "keyboard" => $bot->buildKeyboardOfInline([
            "Light ☀️" => "theme_light",
            "Dark 🌙" => "theme_dark",
            "Auto 🔄" => "theme_auto",
            "← Back" => "settings",
        ]),
    ]);

    // ========================================================================
    // DYNAMIC RESPONSES
    // ========================================================================

    // Language selection
    foreach (['lang_en' => 'English', 'lang_it' => 'Italiano', 'lang_fr' => 'Français'] as $cb => $lang) {
        $bot->simpleCallbackResponse($cb, "✅ Language changed to $lang");
    }

    // Notifications
    $bot->simpleCallbackResponse("notif_on", "🔔 Notifications enabled");
    $bot->simpleCallbackResponse("notif_off", "🔔 Notifications disabled");

    // Themes
    $bot->simpleCallbackResponse("theme_light", "☀️ Light theme enabled");
    $bot->simpleCallbackResponse("theme_dark", "🌙 Dark theme enabled");
    $bot->simpleCallbackResponse("theme_auto", "🔄 Auto theme enabled");

    // ========================================================================
    // NAVIGATION
    // ========================================================================

    $bot->simpleCallbackResponse("menu_main", [
        "text" => "👋 Welcome {{message from first_name}}!\n\nI'm a Telegram bot.",
        "keyboard" => $bot->buildKeyboardOfInline([
            "Profile 👤" => "profile",
            "Settings ⚙️" => "settings",
            "Help ❓" => "help",
        ]),
    ]);

    // ========================================================================
    // HELP CALLBACK
    // ========================================================================

    $bot->simpleCallbackResponse("help", [
        "text" => "❓ Need Help?\n\n" .
                 "I can help you with:\n" .
                 "• Managing your profile\n" .
                 "• Changing settings\n" .
                 "• Checking status",
        "keyboard" => $bot->buildKeyboardOfInline([
            "View Commands" => "view_commands",
            "← Back" => "menu_main",
        ]),
    ]);

    $bot->simpleCallbackResponse("view_commands", [
        "text" => "📋 Commands\n\n" .
                 "/start - Main menu\n" .
                 "/help - Help\n" .
                 "/status - Status",
    ]);

    // ========================================================================
    // USING ALL TELEGRAM BOT API METHODS
    // ========================================================================

    if ($bot->getTextMessage() === "/advanced") {
        // Example: Using ALL Telegram methods via callMethod()
        $result = $bot->callMethod('getChatMember', [
            'chat_id' => $bot->getChatId(),
            'user_id' => $bot->update?->message?->from?->id ?? 0,
        ]);

        if ($result) {
            $bot->sendMessage(
                $bot->getChatId(),
                "User status: " . json_encode($result)
            );
        }
    }

    // Example: Send any Telegram method
    if ($bot->getTextMessage() === "/me") {
        $result = $bot->callMethod('getMe');
        if ($result) {
            $bot->sendMessage(
                $bot->getChatId(),
                "🤖 Bot: @" . ($result['username'] ?? 'unknown')
            );
        }
    }

    // Example: Forward message
    if ($bot->getTextMessage() === "/forward") {
        $bot->forwardMessage(
            chatId: $bot->getChatId(),
            messageId: $bot->getMessageId() ?? 0,
            fromChatId: $bot->getChatId()
        );
    }

    // ========================================================================
    // FALLBACK
    // ========================================================================

    if ($bot->isPrivate() && $bot->getTextMessage()) {
        $userMessage = $bot->getTextMessage();

        // Only for unhandled commands
        if (str_starts_with($userMessage, "/")) {
            $bot->sendMessage(
                $bot->getChatId(),
                "❓ Unknown command: $userMessage\n\nType /help for available commands"
            );
        }
    }
}

// ============================================================================
// CLI HELPER
// ============================================================================

if (php_sapi_name() === 'cli') {
    // Run from command line
    $command = $argv[1] ?? 'start';

    match ($command) {
        'webhook' => {
            echo "🚀 Starting webhook mode...\n";
            $_ENV['BOT_MODE'] = 'webhook';
            webhookMode($token, true);
        },
        'polling' => {
            $_ENV['BOT_MODE'] = 'polling';
            pollingMode($token, true);
        },
        'setwebhook' => {
            $url = $argv[2] ?? 'https://example.com/webhook.php';
            $result = botTG::setWebhook($token, $url);
            echo $result ? "✅ Webhook set\n" : "❌ Failed\n";
        },
        default => {
            echo "TGbotPHP Bot Manager\n\n";
            echo "Usage: php bot-complete.php [command]\n\n";
            echo "Commands:\n";
            echo "  webhook       - Start webhook mode\n";
            echo "  polling       - Start long polling mode\n";
            echo "  setwebhook    - Set webhook URL\n";
        }
    };
}
