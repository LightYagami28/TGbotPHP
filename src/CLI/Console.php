<?php

declare(strict_types=1);

namespace TGbotPHP\CLI;

use TGbotPHP\Core\ApiClient;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Support\Value;
use Throwable;

/**
 * Command line tool
 *
 * The token is read from --token or the TELEGRAM_BOT_TOKEN environment
 * variable (preferred: command line arguments end up in shell history).
 */
class Console
{
    /** @var callable(string): Bot */
    private $botFactory;

    /** @var resource */
    private $output;

    /**
     * @param (callable(string): Bot)|null $botFactory
     * @param resource|null $output
     */
    public function __construct(?callable $botFactory = null, $output = null)
    {
        $this->botFactory = $botFactory ?? static fn(string $token): Bot => new Bot($token);
        $this->output = $output ?? STDOUT;
    }

    /**
     * @param string[] $argv
     * @return int Exit code
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';

        try {
            return match ($command) {
                'help', '--help', '-h' => $this->showHelp(),
                'version', '--version', '-V' => $this->showVersion(),
                'webhook:info' => $this->webhookInfo($argv),
                'webhook:set' => $this->setWebhook($argv),
                'webhook:delete' => $this->deleteWebhook($argv),
                'bot:info', 'bot:test' => $this->botInfo($argv),
                'commands:list' => $this->listCommands($argv),
                'commands:delete' => $this->deleteCommands($argv),
                default => $this->unknown($command),
            };
        } catch (Throwable $e) {
            $this->write('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function showHelp(): int
    {
        $this->write(<<<'EOF'
🤖 TGbotPHP CLI Tool

Usage: tgbot <command> [options]

Commands:
  help                Show this help message
  version             Show version

Webhook Management:
  webhook:info        Get webhook info
  webhook:set         Set webhook URL (--url, optional --secret, --drop-pending)
  webhook:delete      Delete webhook (optional --drop-pending)

Bot Management:
  bot:info            Get bot information (alias: bot:test)
  commands:list       List the bot commands (optional --scope, --lang)
  commands:delete     Delete the bot commands (optional --scope, --lang)

Options:
  --token=TOKEN       Bot token (default: TELEGRAM_BOT_TOKEN environment variable)

Examples:
  TELEGRAM_BOT_TOKEN=<token> tgbot bot:info
  tgbot webhook:set --url=https://example.com/webhook.php --secret="$TELEGRAM_SECRET_TOKEN"
EOF);

        return 0;
    }

    private function showVersion(): int
    {
        $this->write('TGbotPHP v' . ApiClient::VERSION);
        return 0;
    }

    /**
     * @param string[] $argv
     */
    private function webhookInfo(array $argv): int
    {
        $info = $this->bot($argv)->getWebhookInfo();

        $this->write('✅ Webhook Info:');
        $this->write('  URL: ' . Value::string($info['url'] ?? null, 'Not set'));
        $this->write('  Pending updates: ' . Value::int($info['pending_update_count'] ?? null));

        $maxConnections = Value::nullableInt($info['max_connections'] ?? null);
        if ($maxConnections !== null) {
            $this->write('  Max connections: ' . $maxConnections);
        }

        $lastError = Value::nullableString($info['last_error_message'] ?? null);
        if ($lastError !== null) {
            $errorDate = Value::nullableInt($info['last_error_date'] ?? null);
            $date = $errorDate !== null ? date('Y-m-d H:i:s', $errorDate) : 'unknown';
            $this->write("  Last error ($date): " . $lastError);
        }

        return 0;
    }

    /**
     * @param string[] $argv
     */
    private function setWebhook(array $argv): int
    {
        $url = $this->getOption($argv, 'url');

        if ($url === null || $url === '') {
            $this->write('❌ Error: --url is required');
            return 1;
        }

        $this->bot($argv)->setWebhook(
            url: $url,
            dropPendingUpdates: $this->hasFlag($argv, 'drop-pending'),
            secretToken: $this->getOption($argv, 'secret')
        );

        $this->write('✅ Webhook set successfully');
        return 0;
    }

    /**
     * @param string[] $argv
     */
    private function deleteWebhook(array $argv): int
    {
        $this->bot($argv)->deleteWebhook($this->hasFlag($argv, 'drop-pending'));

        $this->write('✅ Webhook deleted successfully');
        return 0;
    }

    /**
     * @param string[] $argv
     */
    private function botInfo(array $argv): int
    {
        $me = $this->bot($argv)->getMe();

        $this->write('✅ Bot Info:');
        $this->write('  ID: ' . Value::string($me['id'] ?? null, '?'));
        $this->write('  Username: @' . Value::string($me['username'] ?? null, '?'));
        $this->write('  Name: ' . Value::string($me['first_name'] ?? null, '?'));
        $this->write('  Can join groups: ' . (($me['can_join_groups'] ?? false) === true ? 'Yes' : 'No'));
        $this->write('  Reads all group messages: ' . (($me['can_read_all_group_messages'] ?? false) === true ? 'Yes' : 'No'));
        $this->write('  Supports inline queries: ' . (($me['supports_inline_queries'] ?? false) === true ? 'Yes' : 'No'));

        return 0;
    }

    /**
     * @param string[] $argv
     */
    private function listCommands(array $argv): int
    {
        $commands = $this->bot($argv)->getMyCommands($this->getOption($argv, 'scope'), $this->getOption($argv, 'lang'));

        if ($commands === []) {
            $this->write('No commands set');
            return 0;
        }

        foreach ($commands as $command) {
            $this->write(sprintf('  /%s - %s', Value::string($command['command'] ?? null), Value::string($command['description'] ?? null)));
        }

        return 0;
    }

    /**
     * @param string[] $argv
     */
    private function deleteCommands(array $argv): int
    {
        $this->bot($argv)->deleteMyCommands($this->getOption($argv, 'scope'), $this->getOption($argv, 'lang'));

        $this->write('✅ Commands deleted');
        return 0;
    }

    private function unknown(string $command): int
    {
        $this->write("❌ Unknown command: $command");
        $this->showHelp();
        return 1;
    }

    /**
     * @param string[] $argv
     */
    private function bot(array $argv): Bot
    {
        $token = $this->getOption($argv, 'token') ?? Value::env('TELEGRAM_BOT_TOKEN');

        if ($token === null || $token === '') {
            throw new \InvalidArgumentException('--token or TELEGRAM_BOT_TOKEN is required');
        }

        return ($this->botFactory)($token);
    }

    /**
     * @param string[] $argv
     */
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

    /**
     * @param string[] $argv
     */
    private function hasFlag(array $argv, string $name): bool
    {
        return in_array("--$name", $argv, true);
    }

    private function write(string $line): void
    {
        fwrite($this->output, $line . PHP_EOL);
    }
}
