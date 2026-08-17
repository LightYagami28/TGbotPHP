# Testing Guide

## Running Tests

### Unit Tests (No API Required)

Run the complete unit test suite:

```bash
bash run-unit-tests.sh
```

**What it tests:**
- ✅ Bot initialization
- ✅ Config validation
- ✅ Update parsing
- ✅ Router registration
- ✅ API method availability

### Webhook Simulation Tests

Test webhook handling without a real Telegram server:

```bash
php tests/webhook-simulator.php
```

**What it tests:**
- ✅ Command handlers (/start, etc)
- ✅ Callback query handling
- ✅ Event dispatching
- ✅ Middleware pipeline

### Integration Tests (Requires Valid Token)

Test actual API calls:

```bash
export TELEGRAM_BOT_TOKEN=your_token_here
php tests/integration-test.php
```

**What it tests:**
- ✅ getMe() - Bot info
- ✅ getWebhookInfo() - Webhook status
- ✅ Method signatures
- ✅ Type hints
- ✅ Security features

## Test Results

```
🧪 TGbotPHP Unit Tests
======================

1️⃣  Testing Bot Initialization...
✅ Bot created
✅ Token set
✅ Config valid

2️⃣  Testing Config Validation...
✅ Invalid config rejected
✅ Valid config created

3️⃣  Testing Update Parser...
✅ Valid update parsed
✅ Invalid update rejected

4️⃣  Testing Router...
✅ Command registered
✅ Callback registered

5️⃣  Testing API Methods...
✅ sendMessage
✅ getChat
✅ banChatMember
✅ sendInvoice
✅ sendGame
✅ getUpdates

✅ All unit tests passed!
```

## Manual Testing

### Test a Real Bot

1. Create `.env`:
```env
TELEGRAM_BOT_TOKEN=your_token_here
DEBUG_MODE=true
DEBUG_LOG_FILE=/tmp/bot.log
```

2. Create `test-bot.php`:
```php
<?php
use TGbotPHP\Framework\Bot;

require_once 'vendor/autoload.php';

$bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));

$bot->command('/start', function($update) use ($bot) {
    $bot->sendMessage(
        $update->message->chat->id,
        "✅ Bot is working!"
    );
});

$bot->handleUpdate(file_get_contents('php://input'));
```

3. Set webhook:
```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -d "url=https://your-domain.com/test-bot.php"
```

4. Send /start to your bot in Telegram

5. Check logs:
```bash
tail -f /tmp/bot.log
```

## Testing Checklist

- [ ] Bot initializes without errors
- [ ] Config validation works
- [ ] Commands are parsed correctly
- [ ] Callbacks are handled
- [ ] Middleware executes in order
- [ ] Events fire properly
- [ ] All API methods exist
- [ ] Type hints are present
- [ ] Error handling works
- [ ] Security features enabled
- [ ] Webhook updates processed
- [ ] Long polling works (if needed)

## Debugging

### Enable Debug Mode

```php
$bot = new Bot(
    token: $token,
    debug: true,
    debugFile: '/var/log/bot.log'
);
```

### Check Bot Info

```php
$me = $bot->getMe();
var_dump($me);
```

### Validate Webhook

```php
$info = $bot->getWebhookInfo();
echo "Webhook URL: " . $info['url'] . "\n";
echo "Pending: " . $info['pending_update_count'] . "\n";
```

### Simulate Webhook Update

```php
$update = json_encode([
    'update_id' => 123,
    'message' => [
        'message_id' => 1,
        'date' => time(),
        'chat' => ['id' => 123, 'type' => 'private'],
        'from' => ['id' => 123, 'first_name' => 'Test'],
        'text' => '/start'
    ]
]);

$bot->handleUpdate($update);
```

## Performance Testing

Monitor performance:

```bash
php -d memory_limit=-1 examples/complete-bot.php
```

Check resource usage:

```bash
ps aux | grep php
```

## Security Testing

Verify security features:

1. **HTTPS Enforcement**
   ```php
   assert($bot->getConfig()->enforceHttps === true);
   ```

2. **Secret Token**
   ```php
   $bot = new Bot($token, secretToken: 'secret123');
   ```

3. **IP Validation**
   ```php
   assert(\TGbotPHP\Core\ApiClient::checkIp('149.154.160.0') === true);
   ```

## CI/CD Integration

### GitHub Actions

```yaml
name: Tests
on: [push]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install
      - run: bash run-unit-tests.sh
      - run: php tests/webhook-simulator.php
```

### Local Pre-commit Hook

```bash
#!/bin/bash
bash run-unit-tests.sh
if [ $? -ne 0 ]; then
  echo "Tests failed!"
  exit 1
fi
```

## Reporting Issues

If tests fail, include:

1. PHP version: `php -v`
2. Composer version: `composer --version`
3. OS: `uname -a`
4. Error output
5. Steps to reproduce

## Success Criteria

✅ All unit tests pass  
✅ No PHP errors or warnings  
✅ Webhook simulation works  
✅ API methods callable  
✅ Security checks pass  
✅ Debug logging works  

## Next Steps

1. ✅ Run unit tests
2. ✅ Run webhook simulator
3. ✅ Test with real Telegram bot
4. ✅ Monitor debug logs
5. ✅ Deploy with confidence!
