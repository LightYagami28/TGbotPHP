<?php

declare(strict_types=1);

/**
 * Secure Telegram Bot Webhook Endpoint
 *
 * Implements all security best practices:
 * - HTTPS enforcement
 * - Token security
 * - Input validation
 * - Webhook signature verification
 * - Rate limiting
 * - Security headers
 */

use TGbotPHP\botTG;

require_once "botlib.php";

// ============================================================================
// SECURITY HEADERS
// ============================================================================

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'none\'');
header('Referrer-Policy: no-referrer');

// ============================================================================
// HTTPS ENFORCEMENT
// ============================================================================

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403);
    exit(json_encode(['error' => 'HTTPS required']));
}

// ============================================================================
// TOKEN MANAGEMENT - SECURE
// ============================================================================

$token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN');

if (!$token || strlen($token) < 10) {
    http_response_code(500);
    error_log('TELEGRAM_BOT_TOKEN not configured or invalid');
    exit(json_encode(['error' => 'Configuration error']));
}

// ============================================================================
// TELEGRAM IP VALIDATION
// ============================================================================

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!botTG::checkIp($clientIp)) {
    http_response_code(403);
    error_log("Unauthorized IP: $clientIp");
    exit(json_encode(['error' => 'Forbidden']));
}

// ============================================================================
// WEBHOOK SIGNATURE VALIDATION
// ============================================================================

$secretToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
$configSecret = $_ENV['TELEGRAM_SECRET_TOKEN'] ?? getenv('TELEGRAM_SECRET_TOKEN');

if ($configSecret && (!$secretToken || !hash_equals($secretToken, $configSecret))) {
    http_response_code(403);
    error_log('Invalid webhook secret token');
    exit(json_encode(['error' => 'Forbidden']));
}

// ============================================================================
// INPUT VALIDATION & PARSING
// ============================================================================

$input = file_get_contents("php://input");

if (empty($input)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Empty request']));
}

// Validate JSON
try {
    $decoded = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    http_response_code(400);
    error_log("Invalid JSON: " . $e->getMessage());
    exit(json_encode(['error' => 'Invalid JSON']));
}

// Validate Telegram update structure
if (!isset($decoded['update_id'])) {
    http_response_code(400);
    error_log("Invalid update: missing update_id");
    exit(json_encode(['error' => 'Invalid update']));
}

// ============================================================================
// RATE LIMITING
// ============================================================================

class RateLimiter
{
    private string $cacheDir;
    private int $window;
    private int $limit;

    public function __construct(string $cacheDir = '/tmp', int $window = 60, int $limit = 10)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->window = $window;
        $this->limit = $limit;
    }

    public function isAllowed(int|string $identifier): bool
    {
        $key = hash('sha256', (string) $identifier);
        $cacheFile = "{$this->cacheDir}/rate_limit_{$key}";

        $now = time();
        $requests = [];

        if (file_exists($cacheFile)) {
            $data = @json_decode(file_get_contents($cacheFile), true) ?? [];
            $requests = array_filter(
                $data['requests'] ?? [],
                fn($time) => $now - $time < $this->window
            );
        }

        if (count($requests) >= $this->limit) {
            return false;
        }

        $requests[] = $now;
        @file_put_contents($cacheFile, json_encode(['requests' => $requests]), LOCK_EX);

        return true;
    }
}

$limiter = new RateLimiter(cacheDir: sys_get_temp_dir());
$chatId = $decoded['message']['chat']['id'] ?? $decoded['callback_query']['message']['chat']['id'] ?? 0;

if ($chatId && !$limiter->isAllowed($chatId)) {
    http_response_code(429);
    error_log("Rate limit exceeded for chat: $chatId");
    exit(json_encode(['error' => 'Rate limited']));
}

// ============================================================================
// BOT INITIALIZATION
// ============================================================================

try {
    $bot = new botTG(
        token: $token,
        updates: $input,
        debug: (bool) (getenv('DEBUG_MODE') === 'true'),
        debugFile: getenv('DEBUG_LOG_FILE') ?: false
    );
} catch (Exception $e) {
    http_response_code(500);
    error_log("Bot init error: " . $e->getMessage());
    exit(json_encode(['error' => 'Internal error']));
}

// ============================================================================
// YOUR BOT LOGIC HERE
// ============================================================================

// Example: Handle /start command
$bot->commandSimple("/start", [
    "text" => "Welcome {{message from first_name}}!",
    "keyboard" => $bot->buildKeyboardOfInline([
        "Help" => "help",
        "Settings" => "settings",
    ]),
]);

// Example: Handle callbacks
$bot->simpleCallbackResponse("help", "Need help? Use /start");

// ============================================================================
// SUCCESS RESPONSE
// ============================================================================

http_response_code(200);
echo json_encode(['ok' => true]);
