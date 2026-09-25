<?php

declare(strict_types=1);

namespace TGbotPHP\Testing;

/**
 * Builds updates as Telegram sends them, to feed a bot in tests
 *
 *     $bot->handleUpdate(FakeUpdate::message('/start'));
 *     $bot->handleUpdate(FakeUpdate::callbackQuery('page:2', chatId: -100123));
 */
final class FakeUpdate
{
    private static int $nextId = 1;

    private function __construct()
    {
        // Static factories only
    }

    /**
     * @param array<string, mixed> $extra Message fields to add or override (entities, reply_to_message...)
     * @return array<string, mixed>
     */
    public static function message(string $text, int $chatId = 42, int $userId = 7, array $extra = []): array
    {
        return self::of('message', array_merge([
            'message_id' => self::$nextId,
            'date' => time(),
            'chat' => self::chat($chatId),
            'from' => self::user($userId),
            'text' => $text,
        ], $extra));
    }

    /**
     * A button press on a message sent by the bot
     *
     * @return array<string, mixed>
     */
    public static function callbackQuery(string $data, int $chatId = 42, int $userId = 7, int $messageId = 5): array
    {
        return self::of('callback_query', [
            'id' => 'cbq-' . self::$nextId,
            'from' => self::user($userId),
            'chat_instance' => 'test',
            'data' => $data,
            'message' => [
                'message_id' => $messageId,
                'date' => time(),
                'chat' => self::chat($chatId),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function inlineQuery(string $query, int $userId = 7): array
    {
        return self::of('inline_query', [
            'id' => 'iq-' . self::$nextId,
            'from' => self::user($userId),
            'query' => $query,
            'offset' => '',
        ]);
    }

    /**
     * Any other update type
     *
     *     FakeUpdate::of('pre_checkout_query', ['id' => 'pcq', 'from' => [...], 'currency' => 'XTR', ...]);
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function of(string $type, array $payload): array
    {
        return ['update_id' => self::$nextId++, $type => $payload];
    }

    /**
     * @return array<string, mixed>
     */
    private static function user(int $userId): array
    {
        return ['id' => $userId, 'is_bot' => false, 'first_name' => 'Test'];
    }

    /**
     * A supergroup for negative ids, a private chat otherwise
     *
     * @return array<string, mixed>
     */
    private static function chat(int $chatId): array
    {
        return $chatId < 0
            ? ['id' => $chatId, 'type' => 'supergroup', 'title' => 'Test group']
            : ['id' => $chatId, 'type' => 'private', 'first_name' => 'Test'];
    }
}
