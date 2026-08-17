<?php

declare(strict_types=1);

use TGbotPHP\botTG;

require_once "botlib.php";

// Get token from environment variable (secure method)
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

// Validate webhook signature if configured
$secretToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
$configSecret = getenv('TELEGRAM_SECRET_TOKEN');

if ($configSecret && $secretToken !== $configSecret) {
    http_response_code(403);
    exit;
}

// Validate input
$updates = file_get_contents("php://input");

try {
    $bot = new botTG(
        token: $token,
        updates: $updates,
        debug: (bool) getenv('DEBUG_MODE'),
        debugFile: getenv('DEBUG_LOG_FILE') ?: false
    );
} catch (Exception $e) {
    http_response_code(400);
    error_log("Bot initialization error: " . $e->getMessage());
    exit;
}

// Build keyboards
$mainKeyboard = $bot->buildKeyboardOfInline([
    "altra schermata→" => "ciao",
    "crea una immagine" => "makeapicture",
]);

$helpKeyboard = $bot->buildKeyboardOfInline(["LIB" => "lib"]);
$mainKeyboard = $bot->mergeMultipleKeyboards([$mainKeyboard, $helpKeyboard]);

$backKeyboard = $bot->buildKeyboardOfInline(["indietro" => "back"]);

$exampleLinkKeyboard = $bot->buildKeyboardOfLinks([
    "ESEMPIO" => "https://github.com/LightYagami28/TGbotPHP/blob/main/botesempio.php"
]);

$classKeyboard = $bot->mergeKeyboards($exampleLinkKeyboard, $backKeyboard);

// Handle /start command
$bot->commandSimple("/start", [
    "text" => "Ciao {{message from first_name}}!",
    "keyboard" => $mainKeyboard,
    "photo" => "start.png",
]);

// Handle "makeapicture" callback
$bot->simpleCallbackResponse("makeapicture", [
    "text" => "VERSION 2.0\nWow, è un esempio di invio di messaggio A PARTE!",
    "keyboard" => $backKeyboard,
    "photo" => "start2.png",
], edit: false);

// Handle "ciao" callback
$bot->simpleCallbackResponse("ciao", [
    "text" => "VERSION 2.0\nWow, che figata questa schermata! Clicca giù per tornare indietro!",
    "keyboard" => $backKeyboard,
]);

// Handle "lib" callback
$bot->simpleCallbackResponse("lib", [
    "text" => "Questa è una semplice CLASSE php, per creare bot più che FACILMENTE!",
    "keyboard" => $classKeyboard,
    "parse_mode" => "markdown",
]);

// Handle "back" callback
$bot->simpleCallbackResponse("back", [
    "text" => "Ciao {{message from first_name}}",
    "keyboard" => $mainKeyboard,
]);

http_response_code(200);
