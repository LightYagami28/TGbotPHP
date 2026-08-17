# Security Policy

## Best Practices

### 1. Webhook Validation
Always validate webhook signatures:
```php
use TGbotPHP\Security\WebhookValidator;

$token = getenv('BOT_SECRET_TOKEN');
$xToken = WebhookValidator::getSecretToken();

if (!WebhookValidator::validate($body, $token, $xToken)) {
    exit('Unauthorized');
}
```

### 2. Rate Limiting
Implement rate limiting:
```php
use TGbotPHP\Rate\RateLimiter;

$limiter = new RateLimiter(new ArrayCache());
if (!$limiter->limit("user:$userId", 10, 60)) {
    // Rate limited
}
```

### 3. Environment Variables
Never commit secrets:
```
.env
.env.local
secrets/
tokens/
```

### 4. HTTPS Only
Always use HTTPS for webhooks.

### 5. Input Validation
Validate all user input before processing.

### 6. Logging
Log security events:
```php
$logger->info('User action', ['user_id' => $userId]);
```

## Reporting Vulnerabilities

Please report security issues responsibly.

## Dependencies

Keep dependencies updated:
```bash
composer update
```
