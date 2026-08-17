<?php
/**
 * TGbotPHP Configuration Example
 *
 * Copy this file to config.local.php and update with your values
 * NEVER commit config.local.php with actual tokens
 */

// Telegram Bot Configuration
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE');
define('TELEGRAM_SECRET_TOKEN', getenv('TELEGRAM_SECRET_TOKEN') ?: '');

// Debug Configuration
define('DEBUG_MODE', getenv('DEBUG_MODE') === 'true' || false);
define('DEBUG_LOG_FILE', __DIR__ . '/logs/debug.log');

// Security Configuration
define('ALLOW_HTTPS_ONLY', true);
define('VALIDATE_WEBHOOK_IP', true);
define('ENABLE_RATE_LIMITING', true);

// Logging Configuration
define('ERROR_LOG_FILE', __DIR__ . '/logs/error.log');
define('LOG_LEVEL', 'WARNING'); // DEBUG, INFO, WARNING, ERROR

// Photo Configuration
define('PHOTOS_DIRECTORY', __DIR__ . '/photos/');
define('ALLOWED_PHOTO_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('MAX_PHOTO_SIZE', 5242880); // 5MB in bytes

// Rate Limiting
define('RATE_LIMIT_REQUESTS', 10);
define('RATE_LIMIT_WINDOW', 60); // seconds

// Webhook Configuration
define('WEBHOOK_URL', getenv('WEBHOOK_URL') ?: 'https://your-domain.com/webhook.php');
define('WEBHOOK_PORT', 443);

// Timezone
date_default_timezone_set('UTC');

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ERROR_LOG_FILE);
}

/**
 * Load local configuration if exists
 * This allows overriding default values
 */
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

/**
 * Validate Configuration
 */
function validateConfiguration() {
    if (empty(TELEGRAM_BOT_TOKEN) || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE') {
        throw new Exception('TELEGRAM_BOT_TOKEN not configured');
    }

    if (!is_dir(PHOTOS_DIRECTORY)) {
        @mkdir(PHOTOS_DIRECTORY, 0755, true);
    }

    if (!is_dir(dirname(DEBUG_LOG_FILE))) {
        @mkdir(dirname(DEBUG_LOG_FILE), 0755, true);
    }

    if (!is_dir(dirname(ERROR_LOG_FILE))) {
        @mkdir(dirname(ERROR_LOG_FILE), 0755, true);
    }
}

// Uncomment to validate on load
// validateConfiguration();
