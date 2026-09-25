<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use JsonSerializable;
use TGbotPHP\Types\InputFile;

/**
 * Sticker methods from Telegram Bot API
 *
 * InputSticker objects may contain an InputFile in their "sticker" field;
 * it is uploaded automatically.
 *
 * @see https://core.telegram.org/bots/api#stickers
 */
trait StickerMethods
{
    use CallsApi;

    /**
     * @param array<string, mixed>|JsonSerializable|null $replyMarkup
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#sendsticker
     */
    public function sendSticker(
        int|string $chatId,
        string|InputFile $sticker,
        array|JsonSerializable|null $replyMarkup = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('sendSticker', [
            'chat_id' => $chatId,
            'sticker' => $sticker,
            'reply_markup' => $replyMarkup,
        ], $options);
    }

    /**
     * @return array<string, mixed> StickerSet
     *
     * @see https://core.telegram.org/bots/api#getstickerset
     */
    public function getStickerSet(string $name): array
    {
        return $this->apiCallObject('getStickerSet', ['name' => $name]);
    }

    /**
     * @param string[] $customEmojiIds
     * @return list<array<string, mixed>>
     *
     * @see https://core.telegram.org/bots/api#getcustomemojistickers
     */
    public function getCustomEmojiStickers(array $customEmojiIds): array
    {
        return $this->apiCallList('getCustomEmojiStickers', [
            'custom_emoji_ids' => array_values($customEmojiIds),
        ]);
    }

    /**
     * Upload a sticker file for later use
     *
     * @param string $stickerFormat "static", "animated" or "video"
     * @return array<string, mixed> File
     *
     * @see https://core.telegram.org/bots/api#uploadstickerfile
     */
    public function uploadStickerFile(int $userId, InputFile $sticker, string $stickerFormat): array
    {
        return $this->apiCallObject('uploadStickerFile', [
            'user_id' => $userId,
            'sticker' => $sticker,
            'sticker_format' => $stickerFormat,
        ]);
    }

    /**
     * Create a new sticker set owned by a user
     *
     * @param array<int, array<string, mixed>> $stickers InputSticker objects (1-50)
     * @param string|null $stickerType "regular", "mask" or "custom_emoji"
     *
     * @see https://core.telegram.org/bots/api#createnewstickerset
     */
    public function createNewStickerSet(
        int $userId,
        string $name,
        string $title,
        array $stickers,
        ?string $stickerType = null,
        bool $needsRepainting = false,
    ): bool {
        return $this->apiCallBool('createNewStickerSet', [
            'user_id' => $userId,
            'name' => $name,
            'title' => $title,
            'stickers' => array_values($stickers),
            'sticker_type' => $stickerType,
            'needs_repainting' => $needsRepainting ? true : null,
        ]);
    }

    /**
     * @param array<string, mixed> $sticker InputSticker object
     *
     * @see https://core.telegram.org/bots/api#addstickertoset
     */
    public function addStickerToSet(int $userId, string $name, array $sticker): bool
    {
        return $this->apiCallBool('addStickerToSet', [
            'user_id' => $userId,
            'name' => $name,
            'sticker' => $sticker,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#setstickerpositioninset
     */
    public function setStickerPositionInSet(string $sticker, int $position): bool
    {
        return $this->apiCallBool('setStickerPositionInSet', [
            'sticker' => $sticker,
            'position' => $position,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletestickerfromset
     */
    public function deleteStickerFromSet(string $sticker): bool
    {
        return $this->apiCallBool('deleteStickerFromSet', ['sticker' => $sticker]);
    }

    /**
     * @param string[] $emojiList
     *
     * @see https://core.telegram.org/bots/api#setstickeremojilist
     */
    public function setStickerEmojiList(string $sticker, array $emojiList): bool
    {
        return $this->apiCallBool('setStickerEmojiList', [
            'sticker' => $sticker,
            'emoji_list' => array_values($emojiList),
        ]);
    }

    /**
     * @param string[] $keywords
     *
     * @see https://core.telegram.org/bots/api#setstickerkeywords
     */
    public function setStickerKeywords(string $sticker, array $keywords = []): bool
    {
        return $this->apiCallBool('setStickerKeywords', [
            'sticker' => $sticker,
            'keywords' => array_values($keywords),
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#setstickersettitle
     */
    public function setStickerSetTitle(string $name, string $title): bool
    {
        return $this->apiCallBool('setStickerSetTitle', [
            'name' => $name,
            'title' => $title,
        ]);
    }

    /**
     * @see https://core.telegram.org/bots/api#deletestickerset
     */
    public function deleteStickerSet(string $name): bool
    {
        return $this->apiCallBool('deleteStickerSet', ['name' => $name]);
    }

    /**
     * Replace an existing sticker in a sticker set with a new one
     *
     * @param array<string, mixed> $sticker
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#replacestickerinset
     */
    public function replaceStickerInSet(
        int $userId,
        string $name,
        string $oldSticker,
        array $sticker,
        array $options = [],
    ): bool {
        return $this->apiCallBool('replaceStickerInSet', [
            'user_id' => $userId,
            'name' => $name,
            'old_sticker' => $oldSticker,
            'sticker' => $sticker,
        ], $options);
    }

    /**
     * Set the thumbnail of a custom emoji sticker set
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setcustomemojistickersetthumbnail
     */
    public function setCustomEmojiStickerSetThumbnail(
        string $name,
        ?string $customEmojiId = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setCustomEmojiStickerSetThumbnail', [
            'name' => $name,
            'custom_emoji_id' => $customEmojiId,
        ], $options);
    }

    /**
     * Change the mask position of a mask sticker
     *
     * @param array<string, mixed>|null $maskPosition
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setstickermaskposition
     */
    public function setStickerMaskPosition(
        string $sticker,
        ?array $maskPosition = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setStickerMaskPosition', [
            'sticker' => $sticker,
            'mask_position' => $maskPosition,
        ], $options);
    }

    /**
     * Set the thumbnail of a regular or mask sticker set
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#setstickersetthumbnail
     */
    public function setStickerSetThumbnail(
        string $name,
        int $userId,
        string $format,
        InputFile|string|null $thumbnail = null,
        array $options = [],
    ): bool {
        return $this->apiCallBool('setStickerSetThumbnail', [
            'name' => $name,
            'user_id' => $userId,
            'format' => $format,
            'thumbnail' => $thumbnail,
        ], $options);
    }
}
