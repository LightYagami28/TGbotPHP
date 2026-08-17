<?php

declare(strict_types=1);

namespace TGbotPHP\CLI;

use TGbotPHP\Framework\Bot;

class Console
{
    public function run(array $argv): void
    {
        $command = $argv[1] ?? 'help';

        match ($command) {
            'help' => $this->showHelp(),
            'version' => $this->showVersion(),
            'webhook:info' => $this->webhookInfo($argv),
            'webhook:set' => $this->setWebhook($argv),
            'webhook:delete' => $this->deleteWebhook($argv),
            'bot:info' => $this->botInfo($argv),
            'bot:test' => $this->testBot($argv),
            default => $this->showHelp(),
        };
    }

    private function showHelp(): void
    {
        echo <<<'EOF'
🤖 TGbotPHP CLI Tool

Usage: tgbot <command> [options]

Commands:
  help              Show this help message
  version           Show version

Webhook Management:
  webhook:info      Get webhook info
  webhook:set       Set webhook URL
  webhook:delete    Delete webhook

Bot Management:
  bot:info          Get bot information
  bot:test          Test bot with /start command

Examples:
  tgbot webhook:info --token=YOUR_TOKEN
  tgbot webhook:set --token=YOUR_TOKEN --url=https://example.com/webhook.php
  tgbot bot:info --token=YOUR_TOKEN

EOF;
    }

    private function showVersion(): void
    {
        echo "TGbotPHP v2.0.0\n";
    }

    private function webhookInfo(array $argv): void
    {
        $token = $this->getOption($argv, 'token');
        if (!$token) {
            echo "❌ Error: --token is required\n";
            return;
        }

        $bot = new Bot($token);
        $info = $bot->getWebhookInfo();

        if ($info) {
            echo "✅ Webhook Info:\n";
            echo "  URL: " . ($info['url'] ? $info['url'] : 'Not set') . "\n";
            echo "  Pending: " . ($info['pending_update_count'] ?? 0) . "\n";
        } else {
            echo "❌ Failed to get webhook info\n";
        }
    }

    private function setWebhook(array $argv): void
    {
        $token = $this->getOption($argv, 'token');
        $url = $this->getOption($argv, 'url');

        if (!$token || !$url) {
            echo "❌ Error: --token and --url are required\n";
            return;
        }

        $bot = new Bot($token);
        $result = $bot->setWebhook($url);

        if ($result) {
            echo "✅ Webhook set successfully\n";
        } else {
            echo "❌ Failed to set webhook\n";
        }
    }

    private function deleteWebhook(array $argv): void
    {
        $token = $this->getOption($argv, 'token');
        if (!$token) {
            echo "❌ Error: --token is required\n";
            return;
        }

        $bot = new Bot($token);
        $result = $bot->deleteWebhook();

        if ($result) {
            echo "✅ Webhook deleted successfully\n";
        } else {
            echo "❌ Failed to delete webhook\n";
        }
    }

    private function botInfo(array $argv): void
    {
        $token = $this->getOption($argv, 'token');
        if (!$token) {
            echo "❌ Error: --token is required\n";
            return;
        }

        $bot = new Bot($token);
        $me = $bot->getMe();

        if ($me) {
            echo "✅ Bot Info:\n";
            echo "  ID: {$me['id']}\n";
            echo "  Username: @{$me['username']}\n";
            echo "  Name: {$me['first_name']}\n";
            echo "  Bot: " . ($me['is_bot'] ? 'Yes' : 'No') . "\n";
        } else {
            echo "❌ Failed to get bot info\n";
        }
    }

    private function testBot(array $argv): void
    {
        $token = $this->getOption($argv, 'token');
        if (!$token) {
            echo "❌ Error: --token is required\n";
            return;
        }

        echo "🧪 Testing bot...\n";

        $bot = new Bot($token);
        $me = $bot->getMe();

        if ($me) {
            echo "✅ Bot is working\n";
            echo "   ID: {$me['id']}\n";
            echo "   Username: @{$me['username']}\n";
        } else {
            echo "❌ Bot test failed\n";
        }
    }

    private function getOption(array $argv, string $name): ?string
    {
        $prefix = "--$name=";
        foreach ($argv as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }
        return null;
    }
}
