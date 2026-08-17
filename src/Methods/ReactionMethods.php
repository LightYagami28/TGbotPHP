<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

use TGbotPHP\Traits\HttpClientTrait;

trait ReactionMethods
{
    use HttpClientTrait;

    public function setMessageReaction(
        int|string $chatId,
        int $messageId,
        array|null $reaction = null,
        bool $isBig = false
    ): bool {
        $result = $this->httpRequest('setMessageReaction', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reaction' => $reaction ? json_encode($reaction) : null,
            'is_big' => $isBig ? 'true' : 'false',
        ], returnResponse: true);

        return $result !== null;
    }

    public function getMessageReactions(
        int|string $chatId,
        int $messageId,
        string|null $emoji = null
    ): array|null {
        return $this->httpRequest('getMessageReactions', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'emoji' => $emoji,
        ], returnResponse: true);
    }
}
