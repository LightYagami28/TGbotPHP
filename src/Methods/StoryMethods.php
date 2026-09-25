<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Story methods from Telegram Bot API
 *
 * Post and manage stories on behalf of a connected business account.
 *
 * @see https://core.telegram.org/bots/api#poststory
 */
trait StoryMethods
{
    use CallsApi;

    /**
     * Delete a story previously posted by the bot on behalf of a managed business account
     *
     * @param array<string, mixed> $options
     *
     * @see https://core.telegram.org/bots/api#deletestory
     */
    public function deleteStory(
        string $businessConnectionId,
        int $storyId,
        array $options = [],
    ): bool {
        return $this->apiCallBool('deleteStory', [
            'business_connection_id' => $businessConnectionId,
            'story_id' => $storyId,
        ], $options);
    }

    /**
     * Edit a story previously posted by the bot on behalf of a managed business account
     *
     * @param array<string, mixed> $content
     * @param array<string, mixed> $options caption, parse_mode, caption_entities, areas
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#editstory
     */
    public function editStory(
        string $businessConnectionId,
        int $storyId,
        array $content,
        array $options = [],
    ): array {
        return $this->apiCallObject('editStory', [
            'business_connection_id' => $businessConnectionId,
            'story_id' => $storyId,
            'content' => $content,
        ], $options);
    }

    /**
     * Post a story on behalf of a managed business account
     *
     * @param array<string, mixed> $content
     * @param array<string, mixed> $options parse_mode, caption_entities, areas, post_to_chat_page, ...
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#poststory
     */
    public function postStory(
        string $businessConnectionId,
        array $content,
        int $activePeriod,
        ?string $caption = null,
        array $options = [],
    ): array {
        return $this->apiCallObject('postStory', [
            'business_connection_id' => $businessConnectionId,
            'content' => $content,
            'active_period' => $activePeriod,
            'caption' => $caption,
        ], $options);
    }

    /**
     * Repost a story on behalf of a business account from another business account
     *
     * @param array<string, mixed> $options post_to_chat_page, protect_content
     * @return array<string, mixed>
     *
     * @see https://core.telegram.org/bots/api#repoststory
     */
    public function repostStory(
        string $businessConnectionId,
        int $fromChatId,
        int $fromStoryId,
        int $activePeriod,
        array $options = [],
    ): array {
        return $this->apiCallObject('repostStory', [
            'business_connection_id' => $businessConnectionId,
            'from_chat_id' => $fromChatId,
            'from_story_id' => $fromStoryId,
            'active_period' => $activePeriod,
        ], $options);
    }
}
