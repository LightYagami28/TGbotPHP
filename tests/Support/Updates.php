<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Support;

use TGbotPHP\Testing\BotTester;

/**
 * Update fixtures
 */
final class Updates
{
    public const string TOKEN = BotTester::TOKEN;

    private static int $nextId = 1000;

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function message(string $text, int $chatId = 42, int $userId = 7, array $extra = []): array
    {
        return [
            'update_id' => self::$nextId++,
            'message' => array_merge([
                'message_id' => 1,
                'date' => 1700000000,
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Test'],
                'text' => $text,
            ], $extra),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function callback(string $data, int $chatId = 42, int $userId = 7): array
    {
        return [
            'update_id' => self::$nextId++,
            'callback_query' => [
                'id' => 'cbq-1',
                'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Test'],
                'chat_instance' => 'x',
                'data' => $data,
                'message' => [
                    'message_id' => 5,
                    'date' => 1700000000,
                    'chat' => ['id' => $chatId, 'type' => 'private'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inlineQuery(string $query, int $userId = 7): array
    {
        return [
            'update_id' => self::$nextId++,
            'inline_query' => [
                'id' => 'iq-1',
                'from' => ['id' => $userId, 'is_bot' => false, 'first_name' => 'Test'],
                'query' => $query,
                'offset' => '',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $update
     */
    public static function json(array $update): string
    {
        return json_encode($update, JSON_THROW_ON_ERROR);
    }
}
