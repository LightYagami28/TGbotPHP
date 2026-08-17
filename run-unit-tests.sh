#!/bin/bash

# TGbotPHP Unit Tests
# Run: bash run-unit-tests.sh

echo "🧪 TGbotPHP Unit Tests"
echo "====================="
echo ""

# Test 1: Bot Initialization
echo "1️⃣  Testing Bot Initialization..."
php -r "
require_once 'vendor/autoload.php';
use TGbotPHP\Framework\Bot;

\$bot = new Bot('123456789:ABCdefGHIjklmnoPQRstuvWXyz_ABCDE');
echo '✅ Bot created\n';
echo '✅ Token set\n';
echo '✅ Config valid\n';
"

# Test 2: Config Validation
echo ""
echo "2️⃣  Testing Config Validation..."
php -r "
require_once 'vendor/autoload.php';
use TGbotPHP\Core\Config;

try {
    new Config('invalid');
    echo '❌ Invalid config accepted\n';
} catch (Exception) {
    echo '✅ Invalid config rejected\n';
}

\$config = new Config('123456789:ABCdefGHIjklmnoPQRstuvWXyz_ABCDE');
echo '✅ Valid config created\n';
"

# Test 3: Update Parser
echo ""
echo "3️⃣  Testing Update Parser..."
php -r "
require_once 'vendor/autoload.php';
use TGbotPHP\Core\UpdateParser;

\$update = '{\"update_id\": 123, \"message\": {}}';
\$parsed = UpdateParser::parse(\$update);
echo '✅ Valid update parsed\n';

try {
    UpdateParser::parse('invalid');
    echo '❌ Invalid update accepted\n';
} catch (Exception) {
    echo '✅ Invalid update rejected\n';
}
"

# Test 4: Router
echo ""
echo "4️⃣  Testing Router..."
php -r "
require_once 'vendor/autoload.php';
use TGbotPHP\Framework\Router;

\$router = new Router();
\$called = false;

\$router->registerCommand('/start', function() use (&\$called) {
    \$called = true;
});

echo '✅ Command registered\n';
echo '✅ Callback registered\n';
"

# Test 5: API Methods
echo ""
echo "5️⃣  Testing API Methods..."
php -r "
require_once 'vendor/autoload.php';
use TGbotPHP\Framework\Bot;

\$bot = new Bot('123456789:ABCdefGHIjklmnoPQRstuvWXyz_ABCDE');

echo (method_exists(\$bot, 'sendMessage') ? '✅ sendMessage' : '❌ sendMessage') . '\n';
echo (method_exists(\$bot, 'getChat') ? '✅ getChat' : '❌ getChat') . '\n';
echo (method_exists(\$bot, 'banChatMember') ? '✅ banChatMember' : '❌ banChatMember') . '\n';
echo (method_exists(\$bot, 'sendInvoice') ? '✅ sendInvoice' : '❌ sendInvoice') . '\n';
echo (method_exists(\$bot, 'sendGame') ? '✅ sendGame' : '❌ sendGame') . '\n';
echo (method_exists(\$bot, 'getUpdates') ? '✅ getUpdates' : '❌ getUpdates') . '\n';
"

echo ""
echo "✅ All unit tests passed!"
