<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

/**
 * Minimal file logger with level threshold
 */
class Logger
{
    private const LEVELS = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];

    private string $logFile;
    private bool $enabled;
    private int $minLevel;

    public function __construct(string $logFile = '/tmp/bot.log', bool $enabled = true, string $minLevel = 'DEBUG')
    {
        $this->logFile = $logFile;
        $this->enabled = $enabled;
        $this->minLevel = self::LEVELS[strtoupper($minLevel)] ?? 0;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * @param array<string, mixed> $context Values replace {placeholders} in the message
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtoupper($level);

        if (!$this->enabled || (self::LEVELS[$level] ?? 0) < $this->minLevel) {
            return;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof \Stringable) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }
        $message = strtr($message, $replacements);

        // Prevent log injection through user supplied values
        $message = str_replace(["\r", "\n"], ['\r', '\n'], $message);

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : '';
        $logLine = "[$timestamp] [$level] $message$contextStr\n";

        error_log($logLine, 3, $this->logFile);
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }
}
