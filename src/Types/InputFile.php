<?php

declare(strict_types=1);

namespace TGbotPHP\Types;

use CURLFile;
use CURLStringFile;
use InvalidArgumentException;

/**
 * A file to upload to Telegram.
 *
 * Plain strings passed to media methods are sent as-is (file_id or HTTP URL).
 * Wrap local files or in-memory contents in an InputFile to upload them:
 *
 *     $bot->sendPhoto($chatId, InputFile::fromPath('/path/photo.jpg'));
 *     $bot->sendDocument($chatId, InputFile::fromContents($csv, 'report.csv'));
 */
final class InputFile
{
    /**
     * @param string|null $path Absolute path of a local file, when uploading from disk
     * @param string|null $contents Raw bytes, when uploading from memory
     * @param string $filename Name reported to Telegram
     * @param string $mimeType MIME type, empty to let cURL guess it
     *
     * @throws InvalidArgumentException When the filename or MIME type is unsafe
     */
    private function __construct(
        private readonly ?string $path,
        private readonly ?string $contents,
        private readonly string $filename,
        private readonly string $mimeType,
    ) {
        if (!self::isSafeFilename($filename) || self::hasControlCharacters($mimeType)) {
            throw new InvalidArgumentException('Invalid upload filename or MIME type');
        }
    }

    /** Reject path separators and control characters in an upload filename. */
    private static function isSafeFilename(string $filename): bool
    {
        return $filename !== '' && preg_match('/[\x00-\x1f\x7f\/\\\\]/', $filename) !== 1;
    }

    private static function hasControlCharacters(string $text): bool
    {
        return preg_match('/[\x00-\x1f\x7f]/', $text) === 1;
    }

    /** Only local, readable files are accepted; stream wrappers are not. */
    private static function isReadableLocalFile(string $path): bool
    {
        return !str_contains($path, '://') && !str_contains($path, "\0") && is_file($path) && is_readable($path);
    }

    /**
     * Upload a file from the local filesystem.
     *
     * @throws InvalidArgumentException When the file is missing or unreadable
     */
    public static function fromPath(string $path, ?string $filename = null, ?string $mimeType = null): self
    {
        if (!self::isReadableLocalFile($path)) {
            throw new InvalidArgumentException("File not found or not readable: $path");
        }

        return new self($path, null, $filename ?? basename($path), $mimeType ?? '');
    }

    /**
     * Upload in-memory contents.
     *
     * @throws InvalidArgumentException When the filename is empty or unsafe
     */
    public static function fromContents(
        string $contents,
        string $filename,
        string $mimeType = 'application/octet-stream',
    ): self {
        if ($filename === '') {
            throw new InvalidArgumentException('Filename cannot be empty');
        }

        return new self(null, $contents, $filename, $mimeType);
    }

    /** Name reported to Telegram for this upload. */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /** Build the cURL object carrying the file contents. */
    public function toCurlFile(): CURLFile|CURLStringFile
    {
        if ($this->path !== null) {
            return new CURLFile($this->path, $this->mimeType, $this->filename);
        }

        return new CURLStringFile((string) $this->contents, $this->filename, $this->mimeType);
    }
}
