<?php

declare(strict_types=1);

namespace TGbotPHP;

use CURLFile;
use JsonException;
use stdClass;

/**
 * Telegram Bot API Wrapper - Secure by Default
 *
 * Production-ready PHP 8.4+ library for building Telegram bots.
 * Implements all security best practices and OWASP guidelines.
 */
#[\Attribute]
class BotEndpoint {}

class botTG
{
    private readonly string $token;
    private readonly bool $debug;
    private readonly string|false $debugFile;
    private readonly string|false $secretToken;
    private readonly bool $enforceHttps;
    public readonly stdClass|null $update;

    private static array $requestCache = [];

    /**
     * Initialize secure Telegram Bot
     *
     * @param string $token Telegram Bot API token
     * @param string|false $updates JSON webhook update (false if none)
     * @param bool $debug Enable debug mode
     * @param string|false $debugFile Log file for errors
     * @param string|false $secretToken Webhook secret token for verification
     * @param bool $enforceHttps Enforce HTTPS for webhooks
     * @throws JsonException
     */
    public function __construct(
        string $token,
        string|false $updates = false,
        bool $debug = false,
        string|false $debugFile = false,
        string|false $secretToken = false,
        bool $enforceHttps = true
    ) {
        if (empty($token) || strlen($token) < 10) {
            throw new \InvalidArgumentException('Invalid Telegram bot token');
        }

        $this->token = $token;
        $this->debug = $debug;
        $this->debugFile = $debugFile;
        $this->secretToken = $secretToken;
        $this->enforceHttps = $enforceHttps;

        // HTTPS enforcement if webhook
        if ($this->enforceHttps && !$this->isCliMode()) {
            $this->enforceHttpsConnection();
        }

        // Validate webhook secret if configured
        if ($secretToken !== false && !$this->validateWebhookSecret($secretToken)) {
            throw new \InvalidArgumentException('Invalid webhook secret token');
        }

        // Parse and validate update
        $this->update = $updates ? $this->parseUpdate($updates) : null;

        // Auto-respond to debug callbacks
        if ($this->debug && $this->update) {
            $this->handleDebugCallbacks();
        }

        // Auto-answer callback queries
        if ($this->update?->callback_query?->id) {
            $this->send('answerCallbackQuery', ['callback_query_id' => $this->update->callback_query->id]);
        }
    }

    /**
     * Check if running in CLI mode
     */
    private function isCliMode(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server';
    }

    /**
     * Enforce HTTPS connection
     */
    private function enforceHttpsConnection(): void
    {
        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            http_response_code(403);
            exit(json_encode(['error' => 'HTTPS required']));
        }
    }

    /**
     * Validate webhook secret token
     */
    private function validateWebhookSecret(string|false $expectedSecret): bool
    {
        if ($expectedSecret === false) {
            return true;
        }

        $receivedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;

        if (!$receivedSecret) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($receivedSecret, $expectedSecret);
    }

    /**
     * Handle debug callback responses
     */
    private function handleDebugCallbacks(): void
    {
        if ($this->update?->callback_query?->data === "nokeyboard") {
            $this->sendEditMessage(
                messageId: $this->update->callback_query->message->message_id,
                chatId: $this->update->callback_query->message->chat->id,
                text: "📕 Error: Missing 'keyboard' parameter in output array"
            );
        } elseif ($this->update?->callback_query?->data === "novalidkeyboard") {
            $this->sendEditMessage(
                messageId: $this->update->callback_query->message->message_id,
                chatId: $this->update->callback_query->message->chat->id,
                text: "📕 Error: Invalid 'keyboard' structure in output array"
            );
        }
    }

    /**
     * Parse and validate webhook update
     *
     * @throws JsonException
     */
    private function parseUpdate(string $updates): ?stdClass
    {
        try {
            $decoded = json_decode($updates, false, 512, JSON_THROW_ON_ERROR);

            // Validate update has required fields
            if (!isset($decoded->update_id)) {
                throw new JsonException('Missing update_id in webhook');
            }

            return $decoded;
        } catch (JsonException $e) {
            if ($this->debug) {
                error_log("Invalid webhook JSON: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Validate Telegram API IP ranges - Anti-spoofing
     *
     * @param string $ip IP address to validate
     * @return bool True if IP is in official Telegram ranges
     */
    public static function checkIp(string $ip): bool
    {
        $ranges = [
            ['address' => '149.154.160.0', 'netmask' => '20', 'netmask_decimal' => -4096, 'integer_id' => '2509938688'],
            ['address' => '91.108.4.0', 'netmask' => '22', 'netmask_decimal' => -1024, 'integer_id' => '1533805568'],
            ['address' => '91.108.56.0', 'netmask' => '21', 'netmask_decimal' => -512, 'integer_id' => '1533894656'],
            ['address' => '45.142.120.0', 'netmask' => '22', 'netmask_decimal' => -1024, 'integer_id' => '759734272'],
        ];

        $ip = trim($ip);
        $ipDecimal = ip2long($ip);

        return match (true) {
            !$ipDecimal => false,
            default => (bool) array_find(
                $ranges,
                fn(array $range) => ($ipDecimal & $range['netmask_decimal']) == $range['integer_id']
            ),
        };
    }

    /**
     * Validate Telegram IP (security check)
     */
    public static function validateTelegramIp(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return self::checkIp($ip);
    }

    /**
     * Forward message between chats
     */
    public function forwardMessage(
        int|string $chatId,
        int $messageId,
        int|string $fromChatId
    ): void {
        $this->send('forwardMessage', [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Forward from reply
     */
    public function forwardMessageFromReply(
        int|string $chatId,
        bool $fromBot = false
    ): void {
        if ($fromBot) {
            if ($this->update?->message?->photo) {
                $this->sendMessage(
                    $chatId,
                    text: $this->update->message->text ?? '',
                    photo: $this->update->message->photo[1]->file_id ?? null
                );
            } else {
                $this->sendMessage($chatId, text: $this->update->message->text ?? '');
            }
        } else {
            $this->send('forwardMessage', [
                'chat_id' => $chatId,
                'from_chat_id' => $this->update?->message?->reply_to_message?->forward_from?->id,
                'message_id' => $this->update?->message?->reply_to_message?->id,
            ]);
        }
    }

    /**
     * Handle callback query response
     */
    public function simpleCallbackResponse(
        string $callbackData,
        array|string $output,
        bool $edit = true
    ): void {
        if ($this->update?->callback_query?->data !== $callbackData) {
            return;
        }

        $config = $this->parseOutputConfig($output);
        $chatId = $this->update->callback_query->message->chat->id;
        $messageId = $this->update->callback_query->message->message_id;

        match ($edit) {
            true => $this->handleEditCallback($config, $chatId, $messageId),
            false => $this->handleNewCallback($config),
        };
    }

    private function handleEditCallback(array $config, int|string $chatId, int $messageId): void
    {
        if ($this->update->callback_query->message?->photo !== null) {
            $this->sendEditMedia($chatId, $messageId, $config);
        } else {
            $this->sendEditMessage(
                $messageId,
                $chatId,
                text: $config['text'],
                parseMode: $config['parse_mode'],
                keyboard: $config['keyboard']
            );
        }
    }

    private function handleNewCallback(array $config): void
    {
        $chatId = $this->update->callback_query->message->chat->id;

        if ($config['photo'] !== null) {
            $this->sendPhoto(
                $chatId,
                $config['photo'],
                text: $config['text'],
                parseMode: $config['parse_mode'],
                keyboard: $config['keyboard']
            );
        } else {
            $this->sendMessage(
                $chatId,
                text: $config['text'],
                parseMode: $config['parse_mode'],
                keyboard: $config['keyboard']
            );
        }
    }

    /**
     * Check if chat is private
     */
    public function isPrivate(): bool
    {
        return match ($this->update?->message?->chat?->type ?? $this->update?->callback_query?->message?->chat?->type) {
            'private' => true,
            default => false,
        };
    }

    /**
     * Get message ID
     */
    public function getMessageId(): int|null
    {
        return $this->update?->message?->message_id;
    }

    /**
     * Get chat ID
     */
    public function getChatId(): int|string|null
    {
        return $this->update?->callback_query?->message?->chat?->id
            ?? $this->update?->message?->chat?->id;
    }

    /**
     * Get text message
     */
    public function getTextMessage(): string|null
    {
        return $this->update?->message?->text;
    }

    /**
     * Get callback data
     */
    public function getData(): string|null
    {
        return $this->update?->callback_query?->data;
    }

    /**
     * Check if text message matches
     */
    public function checkTextMessage(string $messageToCheck): bool
    {
        return ($this->update?->message?->text ?? '') === $messageToCheck;
    }

    /**
     * Check if callback data matches
     */
    public function checkCallbackQueryData(string $dataToCheck): bool
    {
        return ($this->update?->callback_query?->data ?? '') === $dataToCheck;
    }

    /**
     * Edit existing message
     */
    public function editMessage(array|string $output): void
    {
        $config = $this->parseOutputConfig($output);
        $chatId = $this->update->callback_query->message->chat->id;
        $messageId = $this->update->callback_query->message->message_id;

        if ($this->update->callback_query->message?->photo !== null) {
            $this->sendEditMedia($chatId, $messageId, $config);
        } else {
            $this->sendEditMessage(
                $messageId,
                $chatId,
                text: $config['text'],
                parseMode: $config['parse_mode'],
                keyboard: $config['keyboard']
            );
        }
    }

    /**
     * Handle simple command
     */
    public function commandSimple(string $command, array|string $output): void
    {
        $userCommand = $this->update?->message?->text;

        if ($userCommand !== $command) {
            return;
        }

        $config = $this->parseOutputConfig($output);

        if ($config['photo'] !== null) {
            $this->sendPhoto(
                $this->update->message->chat->id,
                $config['photo'],
                text: $config['text'],
                parseMode: $config['parse_mode'],
                keyboard: $config['keyboard']
            );
        } else {
            $this->sendMessage(
                $this->update->message->chat->id,
                text: $config['text'],
                parseMode: $config['parse_mode'],
                keyboard: $config['keyboard']
            );
        }
    }

    /**
     * Send message (safe)
     */
    public function sendMessage(
        int|string $chatId,
        string $text = '',
        string|null $photo = null,
        string $parseMode = 'html',
        array|null $keyboard = null
    ): void {
        $text = $this->applyTemplates($text);

        if ($photo !== null) {
            $this->sendPhoto($chatId, $photo, text: $text, parseMode: $parseMode, keyboard: $keyboard);
        } else {
            $this->send('sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                ...(null !== $keyboard ? ['reply_markup' => json_encode($keyboard)] : []),
            ]);
        }
    }

    /**
     * Send photo (path validated)
     */
    private function sendPhoto(
        int|string $chatId,
        string $photo,
        string $text = '',
        string $parseMode = 'html',
        array|null $keyboard = null
    ): void {
        $photoPath = $this->validatePhotoPath($photo);

        if (!$photoPath) {
            $this->sendMessage($chatId, text: "Error: Photo not found or access denied");
            return;
        }

        $text = $this->applyTemplates($text);

        $this->send('sendPhoto', [
            'chat_id' => $chatId,
            'photo' => new CURLFile($photoPath),
            'caption' => $text,
            'parse_mode' => $parseMode,
            ...(null !== $keyboard ? ['reply_markup' => json_encode($keyboard)] : []),
        ]);
    }

    /**
     * Validate photo path - Prevent directory traversal
     */
    private function validatePhotoPath(string $photo): string|null
    {
        if (empty($photo)) {
            return null;
        }

        $basePath = realpath(__DIR__);
        $photoPath = realpath(__DIR__ . '/' . basename($photo));

        // Security: Ensure file is within project directory
        if (!$photoPath || !is_file($photoPath)) {
            return null;
        }

        if (strpos($photoPath, $basePath) !== 0) {
            return null;
        }

        return $photoPath;
    }

    /**
     * Apply template variables (safe)
     */
    private function applyTemplates(string $text): string
    {
        return strtr($text, [
            '{{message_text}}' => $this->htmlSpecialChars($this->update?->message?->text ?? ''),
            '{{message from first_name}}' => $this->htmlSpecialChars($this->update?->message?->from?->first_name ?? 'User'),
            '{{callback_data}}' => $this->htmlSpecialChars($this->update?->callback_query?->data ?? ''),
        ]);
    }

    /**
     * Safely escape strings
     */
    private function htmlSpecialChars(string $text, bool $double_encode = false): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8', $double_encode);
    }

    /**
     * Parse output configuration
     */
    private function parseOutputConfig(array|string $output): array
    {
        $config = [
            'text' => '',
            'photo' => null,
            'keyboard' => null,
            'parse_mode' => 'html',
        ];

        if (is_string($output)) {
            $config['text'] = $output;
            return $config;
        }

        foreach ($output as $key => $value) {
            match ($key) {
                'text' => $config['text'] = (string) $value,
                'photo' => $config['photo'] = (string) $value,
                'keyboard' => $config['keyboard'] = $value,
                'parse_mode' => $config['parse_mode'] = in_array($value, ['html', 'markdown']) ? (string) $value : 'html',
                default => null,
            };
        }

        return $config;
    }

    /**
     * Send edit message
     */
    private function sendEditMessage(
        int $messageId,
        int|string $chatId,
        string $text = '',
        string $parseMode = 'html',
        array|null $keyboard = null
    ): void {
        $text = $this->applyTemplates($text);

        $this->send('editMessageText', [
            'message_id' => $messageId,
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            ...(null !== $keyboard ? ['reply_markup' => json_encode($keyboard)] : []),
        ]);
    }

    /**
     * Send edit media
     */
    private function sendEditMedia(
        int|string $chatId,
        int $messageId,
        array $config
    ): void {
        $media = [
            'type' => 'photo',
            'media' => $config['photo'] ?? $this->update?->callback_query?->message?->photo[1]->file_id,
            'caption' => $this->applyTemplates($config['text']),
        ];

        $this->send('editMessageMedia', [
            'message_id' => $messageId,
            'chat_id' => $chatId,
            'media' => json_encode($media),
            ...(null !== $config['keyboard'] ? ['reply_markup' => json_encode($config['keyboard'])] : []),
        ]);
    }

    /**
     * Build inline keyboard
     */
    public function buildKeyboardOfInline(array $buttons): array
    {
        $inline = array_map(
            fn(string $text, string $callbackData) => [
                'text' => $this->htmlSpecialChars($text),
                'callback_data' => (string) $callbackData
            ],
            array_keys($buttons),
            array_values($buttons)
        );

        return ['inline_keyboard' => [$inline]];
    }

    /**
     * Build link keyboard
     */
    public function buildKeyboardOfLinks(array $links): array
    {
        $urlKeyb = array_map(
            fn(string $text, string $url) => [
                'text' => $this->htmlSpecialChars($text),
                'url' => filter_var($url, FILTER_VALIDATE_URL) ? (string) $url : '#'
            ],
            array_keys($links),
            array_values($links)
        );

        return ['inline_keyboard' => [$urlKeyb]];
    }

    /**
     * Merge keyboards
     */
    public function mergeKeyboards(array $keyboard1, array $keyboard2): array
    {
        return array_merge_recursive($keyboard1, $keyboard2);
    }

    /**
     * Merge multiple keyboards
     */
    public function mergeMultipleKeyboards(array $keyboards): array
    {
        return array_reduce(
            $keyboards,
            fn(array $carry, array $keyboard) => empty($carry) ? $keyboard : array_merge_recursive($carry, $keyboard),
            []
        );
    }

    /**
     * Call any Telegram Bot API method (supports ALL methods)
     *
     * @param string $method Telegram API method name
     * @param array $data Method parameters
     * @return array|null API response or null on error
     */
    public function callMethod(string $method, array $data = []): array|null
    {
        return $this->send($method, $data, returnResponse: true);
    }

    /**
     * Send API request (secure) - Supports ALL Telegram Bot API methods
     */
    private function send(string $method, array $data, bool $returnResponse = false): array|null|void
    {
        if (empty($method)) {
            throw new \InvalidArgumentException('Method name cannot be empty');
        }

        $url = "https://api.telegram.org/bot{$this->token}/" . urlencode($method);

        $curl = curl_init($url)
            ?? throw new \RuntimeException('Failed to initialize curl');

        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: TGbotPHP/2.0.0',
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($httpCode !== 200 || !$response) {
                $this->handleError($method, $httpCode, $response);
                if ($returnResponse) return null;
                return;
            }

            $decoded = json_decode($response, true);

            if ($decoded['ok'] ?? false) {
                if ($returnResponse) {
                    return $decoded['result'] ?? $decoded;
                }
                return;
            }

            $this->handleError($method, $httpCode, $response);
            if ($returnResponse) return null;
        } finally {
            curl_close($curl);
        }
    }

    /**
     * Get updates via long polling (alternative to webhooks)
     *
     * @param int $offset Last update offset
     * @param int $limit Maximum updates to get (1-100)
     * @param int $timeout Polling timeout in seconds
     * @return array Updates array
     */
    public static function getUpdates(
        string $token,
        int $offset = 0,
        int $limit = 25,
        int $timeout = 30
    ): array {
        if ($limit < 1 || $limit > 100) {
            $limit = 25;
        }

        $url = "https://api.telegram.org/bot{$token}/getUpdates";

        $curl = curl_init($url);
        if (!$curl) {
            throw new \RuntimeException('Failed to initialize curl');
        }

        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    'offset' => $offset,
                    'limit' => $limit,
                    'timeout' => $timeout,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => $timeout + 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $response = curl_exec($curl);
            $decoded = json_decode($response, true);

            return $decoded['result'] ?? [];
        } finally {
            curl_close($curl);
        }
    }

    /**
     * Long polling loop for updates
     *
     * @param callable $handler Callback function to handle updates
     * @param int $pollingInterval Interval between polls in seconds
     */
    public static function pollUpdates(
        string $token,
        callable $handler,
        int $pollingInterval = 1
    ): never {
        $offset = 0;

        while (true) {
            try {
                $updates = self::getUpdates($token, $offset, 25, 30);

                foreach ($updates as $update) {
                    try {
                        $bot = new self(token: $token, updates: json_encode($update));
                        $handler($bot, $update);

                        $offset = $update['update_id'] + 1;
                    } catch (Exception $e) {
                        error_log("Update handler error: " . $e->getMessage());
                    }
                }

                if (empty($updates)) {
                    sleep($pollingInterval);
                }
            } catch (Exception $e) {
                error_log("Polling error: " . $e->getMessage());
                sleep($pollingInterval);
            }
        }
    }

    // ========================================================================
    // TELEGRAM BOT API HELPER METHODS - All documented methods supported
    // ========================================================================
    // Support for ALL official Telegram Bot API methods:
    // https://core.telegram.org/bots/api
    //
    // Usage: $bot->callMethod('methodName', $parameters)
    // Example: $bot->callMethod('sendMessage', ['chat_id' => 123, 'text' => 'Hi'])
    //
    // Popular shortcuts below, but ALL methods work via callMethod()
    // ========================================================================

    /**
     * Set webhook URL for updates
     * @see https://core.telegram.org/bots/api#setwebhook
     */
    public static function setWebhook(string $token, string $url, string|false $secretToken = false): bool
    {
        $data = ['url' => $url];
        if ($secretToken !== false) {
            $data['secret_token'] = $secretToken;
        }

        $url = "https://api.telegram.org/bot{$token}/setWebhook";
        $curl = curl_init($url);
        if (!$curl) return false;

        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = json_decode(curl_exec($curl) ?? '', true);
            return $response['ok'] ?? false;
        } finally {
            curl_close($curl);
        }
    }

    /**
     * Remove webhook and switch to polling
     * @see https://core.telegram.org/bots/api#deletewebhook
     */
    public static function deleteWebhook(string $token): bool
    {
        $url = "https://api.telegram.org/bot{$token}/deleteWebhook";
        $curl = curl_init($url);
        if (!$curl) return false;

        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = json_decode(curl_exec($curl) ?? '', true);
            return $response['ok'] ?? false;
        } finally {
            curl_close($curl);
        }
    }

    /**
     * Get webhook info
     * @see https://core.telegram.org/bots/api#getwebhookinfo
     */
    public static function getWebhookInfo(string $token): array|null
    {
        $url = "https://api.telegram.org/bot{$token}/getWebhookInfo";
        $curl = curl_init($url);
        if (!$curl) return null;

        try {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = json_decode(curl_exec($curl) ?? '', true);
            return $response['result'] ?? null;
        } finally {
            curl_close($curl);
        }
    }

    /**
     * Get bot info
     * @see https://core.telegram.org/bots/api#getme
     */
    public function getMe(): array|null
    {
        return $this->callMethod('getMe');
    }

    /**
     * Send text message with formatting
     * @see https://core.telegram.org/bots/api#sendmessage
     */
    public function sendTextMessage(
        int|string $chatId,
        string $text,
        string $parseMode = 'HTML',
        bool $disableWebPagePreview = false,
        bool $disableNotification = false,
        bool $protectContent = false,
        int|null $replyToMessageId = null
    ): array|null {
        return $this->callMethod('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => $disableWebPagePreview,
            'disable_notification' => $disableNotification,
            'protect_content' => $protectContent,
            'reply_to_message_id' => $replyToMessageId,
        ]);
    }

    /**
     * Send document/file
     * @see https://core.telegram.org/bots/api#senddocument
     */
    public function sendDocument(
        int|string $chatId,
        string $filePath,
        string|null $caption = null
    ): array|null {
        return $this->callMethod('sendDocument', [
            'chat_id' => $chatId,
            'document' => new CURLFile($filePath),
            'caption' => $caption,
        ]);
    }

    /**
     * Send audio file
     * @see https://core.telegram.org/bots/api#sendaudio
     */
    public function sendAudio(
        int|string $chatId,
        string $filePath,
        string|null $caption = null,
        int|null $duration = null
    ): array|null {
        return $this->callMethod('sendAudio', [
            'chat_id' => $chatId,
            'audio' => new CURLFile($filePath),
            'caption' => $caption,
            'duration' => $duration,
        ]);
    }

    /**
     * Send video
     * @see https://core.telegram.org/bots/api#sendvideo
     */
    public function sendVideo(
        int|string $chatId,
        string $filePath,
        string|null $caption = null,
        int|null $duration = null,
        int|null $width = null,
        int|null $height = null
    ): array|null {
        return $this->callMethod('sendVideo', [
            'chat_id' => $chatId,
            'video' => new CURLFile($filePath),
            'caption' => $caption,
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Send animation (GIF/MP4)
     * @see https://core.telegram.org/bots/api#sendanimation
     */
    public function sendAnimation(
        int|string $chatId,
        string $filePath,
        string|null $caption = null
    ): array|null {
        return $this->callMethod('sendAnimation', [
            'chat_id' => $chatId,
            'animation' => new CURLFile($filePath),
            'caption' => $caption,
        ]);
    }

    /**
     * Send voice message
     * @see https://core.telegram.org/bots/api#sendvoice
     */
    public function sendVoice(
        int|string $chatId,
        string $filePath,
        string|null $caption = null
    ): array|null {
        return $this->callMethod('sendVoice', [
            'chat_id' => $chatId,
            'voice' => new CURLFile($filePath),
            'caption' => $caption,
        ]);
    }

    /**
     * Send sticker
     * @see https://core.telegram.org/bots/api#sendsticker
     */
    public function sendSticker(
        int|string $chatId,
        string $stickerId
    ): array|null {
        return $this->callMethod('sendSticker', [
            'chat_id' => $chatId,
            'sticker' => $stickerId,
        ]);
    }

    /**
     * Get file info and download URL
     * @see https://core.telegram.org/bots/api#getfile
     */
    public function getFile(string $fileId): array|null
    {
        return $this->callMethod('getFile', ['file_id' => $fileId]);
    }

    /**
     * Get chat info
     * @see https://core.telegram.org/bots/api#getchat
     */
    public function getChat(int|string $chatId): array|null
    {
        return $this->callMethod('getChat', ['chat_id' => $chatId]);
    }

    /**
     * Get chat member info
     * @see https://core.telegram.org/bots/api#getchatmember
     */
    public function getChatMember(int|string $chatId, int $userId): array|null
    {
        return $this->callMethod('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Kick user from chat
     * @see https://core.telegram.org/bots/api#kickchatmember
     */
    public function kickChatMember(int|string $chatId, int $userId, int|null $untilDate = null): bool
    {
        $result = $this->callMethod('kickChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'until_date' => $untilDate,
        ]);
        return $result !== null;
    }

    /**
     * Ban user from chat
     * @see https://core.telegram.org/bots/api#banchatmember
     */
    public function banChatMember(int|string $chatId, int $userId): bool
    {
        $result = $this->callMethod('banChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
        return $result !== null;
    }

    /**
     * Unban user from chat
     * @see https://core.telegram.org/bots/api#unbanchatmember
     */
    public function unbanChatMember(int|string $chatId, int $userId): bool
    {
        $result = $this->callMethod('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
        return $result !== null;
    }

    /**
     * Leave chat
     * @see https://core.telegram.org/bots/api#leavechat
     */
    public function leaveChat(int|string $chatId): bool
    {
        $result = $this->callMethod('leaveChat', ['chat_id' => $chatId]);
        return $result !== null;
    }

    /**
     * Delete message
     * @see https://core.telegram.org/bots/api#deletemessage
     */
    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        $result = $this->callMethod('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
        return $result !== null;
    }

    /**
     * Pin message
     * @see https://core.telegram.org/bots/api#pinmessage
     */
    public function pinMessage(int|string $chatId, int $messageId): bool
    {
        $result = $this->callMethod('pinMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
        return $result !== null;
    }

    /**
     * Unpin message
     * @see https://core.telegram.org/bots/api#unpinmessage
     */
    public function unpinMessage(int|string $chatId, int|null $messageId = null): bool
    {
        $result = $this->callMethod('unpinMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
        return $result !== null;
    }

    /**
     * Create invoice for payment
     * @see https://core.telegram.org/bots/api#sendinvoice
     */
    public function sendInvoice(
        int $userId,
        string $title,
        string $description,
        string $payload,
        string $providerToken,
        string $currency,
        array $prices
    ): array|null {
        return $this->callMethod('sendInvoice', [
            'chat_id' => $userId,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'provider_token' => $providerToken,
            'currency' => $currency,
            'prices' => $prices,
        ]);
    }

    /**
     * Create game score
     * @see https://core.telegram.org/bots/api#setgamescore
     */
    public function setGameScore(int $userId, int $score, int $messageId): array|null
    {
        return $this->callMethod('setGameScore', [
            'user_id' => $userId,
            'score' => $score,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Answer inline query (search results)
     * @see https://core.telegram.org/bots/api#answerinlinequery
     */
    public function answerInlineQuery(
        string $inlineQueryId,
        array $results,
        int|null $cacheTime = null
    ): bool {
        $result = $this->callMethod('answerInlineQuery', [
            'inline_query_id' => $inlineQueryId,
            'results' => $results,
            'cache_time' => $cacheTime,
        ]);
        return $result !== null;
    }

    /**
     * Set chat title
     * @see https://core.telegram.org/bots/api#setchattitle
     */
    public function setChatTitle(int|string $chatId, string $title): bool
    {
        $result = $this->callMethod('setChatTitle', [
            'chat_id' => $chatId,
            'title' => $title,
        ]);
        return $result !== null;
    }

    /**
     * Set chat description
     * @see https://core.telegram.org/bots/api#setchatdescription
     */
    public function setChatDescription(int|string $chatId, string $description): bool
    {
        $result = $this->callMethod('setChatDescription', [
            'chat_id' => $chatId,
            'description' => $description,
        ]);
        return $result !== null;
    }

    /**
     * Handle API errors safely
     */
    private function handleError(string $method, int $httpCode, string|bool $response): void
    {
        $errorMessage = match (true) {
            !$response => "Empty response from $method",
            default => match ($decoded = json_decode($response, true)) {
                null => "Invalid JSON response",
                default => $decoded['description'] ?? "Unknown error from $method",
            },
        };

        if ($this->debug) {
            $this->logError("[$method] HTTP $httpCode: $errorMessage");

            if ($this->debugFile !== false) {
                $this->writeErrorLog($errorMessage);
            }
        }
    }

    /**
     * Log error safely
     */
    private function logError(string $message): void
    {
        error_log($message);
    }

    /**
     * Write error to log file (safe)
     */
    private function writeErrorLog(string $message): void
    {
        if ($this->debugFile === false) {
            return;
        }

        $dir = dirname($this->debugFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $handle = @fopen($this->debugFile, 'a');
        if ($handle) {
            fwrite($handle, sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message));
            fclose($handle);
        }
    }
}
