<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\E2E;

use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Support\Value;
use TGbotPHP\Types\InputFile;
use TGbotPHP\Utilities\Formatter;
use TGbotPHP\Utilities\InlineKeyboard;
use TGbotPHP\Utilities\Keyboard;

/**
 * Methods that send to a real chat (TELEGRAM_TEST_CHAT_ID)
 *
 * Every message sent is deleted when the test ends.
 */
final class MessagingTest extends TelegramTestCase
{
    /** @var list<int> */
    private array $sent = [];

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->sent === []) {
            return;
        }

        try {
            $this->bot->deleteMessages($this->chatId(), $this->sent);
        } catch (ApiException) {
            // One undeletable message fails the whole batch: retry one by one
            foreach ($this->sent as $messageId) {
                try {
                    $this->bot->deleteMessage($this->chatId(), $messageId);
                } catch (ApiException) {
                    // e.g. dice messages in private chats can only be deleted after 24 hours
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function track(array $message): array
    {
        $id = Value::nullableInt($message['message_id'] ?? null);
        self::assertNotNull($id, 'The result is not a Message');
        $this->sent[] = $id;

        return $message;
    }

    /**
     * @param array<string, mixed> $message
     */
    private static function messageId(array $message): int
    {
        return Value::int($message['message_id'] ?? null);
    }

    public function testSendEditAndDeleteText(): void
    {
        $chatId = $this->chatId();
        $keyboard = InlineKeyboard::make()->button('One', 'e2e:1')->button('Two', 'e2e:2')->row()->url('Docs', 'https://core.telegram.org/bots/api');

        // Quotes and apostrophes must survive HTML escaping (Telegram rejects &apos;)
        $text = Formatter::bold('TGbotPHP E2E') . "\n" . Formatter::escape("it's <not> a \"tag\" & more");
        $message = $this->track($this->bot->sendMessage($chatId, $text, replyMarkup: $keyboard));

        self::assertSame("TGbotPHP E2E\nit's <not> a \"tag\" & more", $message['text'] ?? null);
        self::assertSame('e2e:1', Value::path($message, 'reply_markup', 'inline_keyboard', '0', '0', 'callback_data'));

        // Without replyMarkup Telegram would drop the buttons: pass them again to keep them
        $edited = $this->bot->editMessageText($chatId, self::messageId($message), Formatter::italic('edited'), replyMarkup: $keyboard);
        self::assertIsArray($edited);
        self::assertSame('edited', $edited['text'] ?? null);
        self::assertSame('e2e:1', Value::path($edited, 'reply_markup', 'inline_keyboard', '0', '0', 'callback_data'));

        $withoutKeyboard = $this->bot->editMessageReplyMarkup($chatId, self::messageId($message));
        self::assertIsArray($withoutKeyboard);
        self::assertArrayNotHasKey('reply_markup', $withoutKeyboard);

        self::assertTrue($this->bot->sendChatAction($chatId, 'typing'));
    }

    public function testReplyKeyboardAndOptions(): void
    {
        $chatId = $this->chatId();

        $message = $this->track($this->bot->sendMessage($chatId, 'Reply keyboard', replyMarkup: Keyboard::reply([['Yes', 'No']], oneTime: true), options: [
            'protect_content' => true,
        ]));
        self::assertTrue($message['has_protected_content'] ?? null);

        $this->track($this->bot->sendMessage($chatId, 'Keyboard removed', replyMarkup: Keyboard::remove(), disableNotification: true));

        $reply = $this->track($this->bot->sendMessage($chatId, 'Reply to the first message', options: [
            'reply_parameters' => ['message_id' => self::messageId($message)],
        ]));
        self::assertSame(self::messageId($message), Value::path($reply, 'reply_to_message', 'message_id'));
    }

    public function testDocumentUploadAndDownload(): void
    {
        $chatId = $this->chatId();
        $contents = "id,name\n1,TGbotPHP\n2," . bin2hex(random_bytes(8)) . "\n";

        $message = $this->track($this->bot->sendDocument($chatId, InputFile::fromContents($contents, 'e2e.csv', 'text/csv'), 'CSV upload'));
        $fileId = Value::string(Value::path($message, 'document', 'file_id'));
        self::assertNotSame('', $fileId);
        self::assertSame('e2e.csv', Value::path($message, 'document', 'file_name'));

        // Round trip: the downloaded bytes are exactly what was uploaded
        self::assertSame($contents, $this->bot->downloadFile($fileId));

        // Streamed to disk, through the same connection
        $destination = sys_get_temp_dir() . '/tgbotphp-e2e-' . bin2hex(random_bytes(4)) . '.csv';
        try {
            self::assertSame($destination, $this->bot->downloadFile($fileId, $destination));
            self::assertSame($contents, file_get_contents($destination));
        } finally {
            @unlink($destination);
        }

        // Re-send by file_id: no upload
        $resent = $this->track($this->bot->sendDocument($chatId, $fileId, 'Re-sent by file_id'));
        self::assertSame(Value::path($message, 'document', 'file_unique_id'), Value::path($resent, 'document', 'file_unique_id'));
    }

    public function testPhotoUploadFromPath(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('The GD extension is needed to generate a test image');
        }

        $image = imagecreatetruecolor(320, 200);
        self::assertNotFalse($image);
        $color = imagecolorallocate($image, 36, 161, 222);
        self::assertNotFalse($color);
        imagefilledrectangle($image, 0, 0, 319, 199, $color);
        $path = sys_get_temp_dir() . '/tgbotphp-e2e-' . bin2hex(random_bytes(4)) . '.png';
        imagepng($image, $path);

        try {
            $message = $this->track($this->bot->sendPhoto($this->chatId(), InputFile::fromPath($path), Formatter::bold('Photo') . ' upload'));
        } finally {
            unlink($path);
        }

        $sizes = Value::path($message, 'photo');
        self::assertIsArray($sizes);
        self::assertNotSame([], $sizes);
        self::assertSame('Photo upload', $message['caption'] ?? null);
    }

    public function testMediaGroupWithUploads(): void
    {
        $messages = $this->bot->sendMediaGroup($this->chatId(), [
            ['type' => 'document', 'media' => InputFile::fromContents('first', 'first.txt', 'text/plain')],
            ['type' => 'document', 'media' => InputFile::fromContents('second', 'second.txt', 'text/plain'), 'caption' => 'Album'],
        ]);

        self::assertCount(2, $messages);
        foreach ($messages as $message) {
            $this->track($message);
        }
        self::assertSame(['first.txt', 'second.txt'], array_map(static fn(array $m): mixed => Value::path($m, 'document', 'file_name'), $messages));
    }

    public function testPollDiceReactionPinCopyForward(): void
    {
        $chatId = $this->chatId();

        $poll = $this->track($this->bot->sendPoll($chatId, 'TGbotPHP E2E poll?', ['Yes', 'No', 'Maybe']));
        $stopped = $this->bot->stopPoll($chatId, self::messageId($poll));
        self::assertTrue($stopped['is_closed'] ?? null);
        $options = $stopped['options'] ?? null;
        self::assertIsArray($options);
        self::assertCount(3, $options);

        // Not tracked: dice messages in private chats cannot be deleted for 24 hours
        $dice = $this->bot->sendDice($chatId, '🎲');
        self::assertIsInt(Value::path($dice, 'dice', 'value'));

        $text = $this->track($this->bot->sendMessage($chatId, 'React, pin, copy and forward me'));
        self::assertTrue($this->bot->setMessageReaction($chatId, self::messageId($text), ['👍']));
        self::assertTrue($this->bot->pinChatMessage($chatId, self::messageId($text), disableNotification: true));
        self::assertTrue($this->bot->unpinChatMessage($chatId, self::messageId($text)));

        $copy = $this->bot->copyMessage($chatId, $chatId, self::messageId($text));
        $this->sent[] = Value::int($copy['message_id'] ?? null);

        $forwarded = $this->track($this->bot->forwardMessage($chatId, $chatId, self::messageId($text)));
        self::assertSame('React, pin, copy and forward me', $forwarded['text'] ?? null);
    }

    public function testChatInformation(): void
    {
        $chat = $this->bot->getChat($this->chatId());
        self::assertSame('private', $chat['type'] ?? null);

        $member = $this->bot->getChatMember($this->chatId(), Value::int($this->chatId()));
        self::assertSame('member', $member['status'] ?? null);
    }
}
