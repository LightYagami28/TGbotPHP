<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use CURLFile;
use TGbotPHP\Traits\HttpClientTrait;

/**
 * Sticker methods from Telegram Bot API
 *
 * @see https://core.telegram.org/bots/api#stickers
 */
trait StickerMethods
{
    use HttpClientTrait;

    /**
     * Send sticker
     *
     * @see https://core.telegram.org/bots/api#sendsticker
     */
    public function sendSticker(
        int|string $chatId,
        string $sticker,
        array|null $replyMarkup = null
    ): array|null {
        return $this->httpRequest('sendSticker', [
            'chat_id' => $chatId,
            'sticker' => $sticker,
            ...(null !== $replyMarkup ? ['reply_markup' => json_encode($replyMarkup)] : []),
        ], returnResponse: true);
    }

    /**
     * Get sticker set
     *
     * @see https://core.telegram.org/bots/api#getstickerset
     */
    public function getStickerSet(string $name): array|null
    {
        return $this->httpRequest('getStickerSet', [
            'name' => $name,
        ], returnResponse: true);
    }

    /**
     * Get custom emoji stickers
     *
     * @see https://core.telegram.org/bots/api#getcustomemojistickers
     */
    public function getCustomEmojiStickers(array $customEmojiIds): array|null
    {
        return $this->httpRequest('getCustomEmojiStickers', [
            'custom_emoji_ids' => json_encode($customEmojiIds),
        ], returnResponse: true);
    }

    /**
     * Upload sticker file
     *
     * @see https://core.telegram.org/bots/api#uploadstickerfile
     */
    public function uploadStickerFile(
        int $userId,
        string $pngSticker,
        string $stickerFormat
    ): array|null {
        return $this->httpRequest('uploadStickerFile', [
            'user_id' => $userId,
            'sticker' => new CURLFile($pngSticker),
            'sticker_format' => $stickerFormat,
        ], returnResponse: true);
    }

    /**
     * Create new sticker set
     *
     * @see https://core.telegram.org/bots/api#createnewstickerset
     */
    public function createNewStickerSet(
        int $userId,
        string $name,
        string $title,
        string $sticker,
        string $stickerFormat,
        string|null $emojis = null,
        bool $containsMasks = false,
        array|null $maskPosition = null
    ): bool {
        $result = $this->httpRequest('createNewStickerSet', [
            'user_id' => $userId,
            'name' => $name,
            'title' => $title,
            'sticker' => new CURLFile($sticker),
            'sticker_format' => $stickerFormat,
            'emojis' => $emojis,
            'contains_masks' => $containsMasks ? 'true' : 'false',
            'mask_position' => $maskPosition ? json_encode($maskPosition) : null,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Add sticker to set
     *
     * @see https://core.telegram.org/bots/api#addstickertoset
     */
    public function addStickerToSet(
        int $userId,
        string $name,
        string $sticker,
        string $emojis,
        array|null $maskPosition = null
    ): bool {
        $result = $this->httpRequest('addStickerToSet', [
            'user_id' => $userId,
            'name' => $name,
            'sticker' => new CURLFile($sticker),
            'emojis' => $emojis,
            'mask_position' => $maskPosition ? json_encode($maskPosition) : null,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Set sticker position in set
     *
     * @see https://core.telegram.org/bots/api#setstickerpositioninset
     */
    public function setStickerPositionInSet(string $sticker, int $position): bool
    {
        $result = $this->httpRequest('setStickerPositionInSet', [
            'sticker' => $sticker,
            'position' => $position,
        ], returnResponse: true);

        return $result !== null;
    }

    /**
     * Delete sticker from set
     *
     * @see https://core.telegram.org/bots/api#deletestickerfromset
     */
    public function deleteStickerFromSet(string $sticker): bool
    {
        $result = $this->httpRequest('deleteStickerFromSet', [
            'sticker' => $sticker,
        ], returnResponse: true);

        return $result !== null;
    }
}
