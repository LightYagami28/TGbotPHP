<?php

declare(strict_types=1);

namespace TGbotPHP\Types;

use CURLFile;
use CURLStringFile;
use InvalidArgumentException;

/**
 * A file to upload to Telegram
 *
 * Plain strings passed to media methods are sent as-is (file_id or HTTP URL).
 * Wrap local files or in-memory contents in an InputFile to upload them:
 *
 *     $bot->sendPhoto($chatId, InputFile::fromPath('/path/photo.jpg'));
 *     $bot->sendDocument($chatId, InputFile::fromContents($csv, 'report.csv'));
 */
final class InputFile
{
    private function __construct(
        private readonly ?string $path,
        private readonly ?string $contents,
        private readonly string $filename,
        private readonly string $mimeType,
    ) {}

    /**
     * Upload a file from the local filesystem
     */
    public static function fromPath(string $path, ?string $filename = null, ?string $mimeType = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException("File not found or not readable: $path");
        }

        return new self($path, null, $filename ?? basename($path), $mimeType ?? '');
    }

    /**
     * Upload in-memory contents
     */
    public static function fromContents(string $contents, string $filename, string $mimeType = 'application/octet-stream'): self
    {
        if ($filename === '') {
            throw new InvalidArgumentException('Filename cannot be empty');
        }

        return new self(null, $contents, $filename, $mimeType);
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function toCurlFile(): CURLFile|CURLStringFile
    {
        if ($this->path !== null) {
            return new CURLFile($this->path, $this->mimeType, $this->filename);
        }

        return new CURLStringFile((string) $this->contents, $this->filename, $this->mimeType);
    }
}
