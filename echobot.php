<?php

declare(strict_types=1);

use TGbotPHP\botTG;

require_once "botlib.php";

// Get token from environment
$token = getenv('TELEGRAM_BOT_TOKEN') ?? $_SERVER['TELEGRAM_BOT_TOKEN'] ?? null;

if (!$token) {
    http_response_code(500);
    error_log('TELEGRAM_BOT_TOKEN not configured');
    exit;
}

// Enforce HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403);
    exit('HTTPS required');
}

// Validate Telegram IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!botTG::checkIp($ip)) {
    http_response_code(403);
    error_log("Invalid IP: $ip");
    exit;
}

// Initialize bot
try {
    $updates = file_get_contents("php://input");
    $bot = new botTG(token: $token, updates: $updates, debug: false);
} catch (Exception $e) {
    http_response_code(400);
    error_log("Bot initialization error: " . $e->getMessage());
    exit;
}

// Echo only in private chats
if ($bot->isPrivate()) {
    $message = $bot->getTextMessage();
    if ($message) {
        $bot->commandSimple(text: $message, output: $message);
    }
}

http_response_code(200);
