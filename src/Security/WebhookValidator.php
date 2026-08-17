<?php

declare(strict_types=1);

namespace TGbotPHP\Security;

class WebhookValidator
{
    public static function validate(string $body, string $secretToken, ?string $xTelegramBotApiSecretToken = null): bool
    {
        if (!$secretToken) {
            return true;
        }

        if (!$xTelegramBotApiSecretToken) {
            return false;
        }

        return hash_equals($secretToken, $xTelegramBotApiSecretToken);
    }

    public static function validateSignature(string $body, string $signature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $body, $secret);
        return hash_equals($signature, $expectedSignature);
    }

    public static function getSecretToken(): ?string
    {
        return $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
    }
}
