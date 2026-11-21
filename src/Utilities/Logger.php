<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

use Stringable;
use TGbotPHP\Security\LogRedactor;
use TGbotPHP\Support\PrivateFile;

/**
 * Minimal file logger with a level threshold and credential redaction.
 */
class Logger
{
    /** Numeric ordering of the supported levels. */
    private const array LEVELS = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];

    private string $logFile;
    private bool $enabled;
    private int $minLevel;

    /**
     * @param string $logFile A file path or a stream such as php://stderr (the default,
     *     collected by Docker and systemd)
     * @param bool $enabled Whether logging is active
     * @param string $minLevel Lowest level to emit: DEBUG, INFO, WARNING or ERROR
     */
    public function __construct(string $logFile = 'php://stderr', bool $enabled = true, string $minLevel = 'DEBUG')
    {
        $this->logFile = $logFile;
        $this->enabled = $enabled;
        $this->minLevel = self::LEVELS[strtoupper($minLevel)] ?? 0;
    }

    /**
     * @param array<string, mixed> $context Values replace {placeholders} in the message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context Values replace {placeholders} in the message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * @param array<string, mixed> $context Values replace {placeholders} in the message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context Values replace {placeholders} in the message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Write a redacted, single-line log entry when the level passes the threshold.
     *
     * @param array<string, mixed> $context Values replace {placeholders} in the message
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtoupper($level);
        $level = isset(self::LEVELS[$level]) ? $level : 'DEBUG';

        if (!$this->enabled || self::LEVELS[$level] < $this->minLevel) {
            return;
        }

        $context = LogRedactor::context($context);
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp] [$level] " . self::message($message, $context) . self::contextSuffix($context) . "\n";

        PrivateFile::create($this->logFile);
        error_log($logLine, 3, $this->logFile);
    }

    /**
     * @param array<mixed> $context Already redacted context
     */
    private static function message(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof Stringable) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }
        $message = LogRedactor::text(strtr($message, $replacements));

        // Prevent log injection through user supplied values
        return str_replace(["\r", "\n"], ['\r', '\n'], $message);
    }

    /**
     * @param array<mixed> $context Already redacted context
     */
    private static function contextSuffix(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;

        return ' ' . json_encode($context, $flags);
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
