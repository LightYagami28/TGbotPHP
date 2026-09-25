<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

use JsonException;
use stdClass;
use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Core\Config;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Exceptions\InvalidTokenException;
use TGbotPHP\Exceptions\NetworkException;
use TGbotPHP\Exceptions\TooManyRequestsException;
use TGbotPHP\Http\TransportInterface;
use TGbotPHP\Plugin\BotPluginInterface;
use TGbotPHP\Plugin\PluginInterface;
use TGbotPHP\Plugin\PluginManager;
use TGbotPHP\Security\WebhookValidator;
use TGbotPHP\Session\ConversationManager;
use TGbotPHP\Support\Value;
use Throwable;

/**
 * Main Bot class - orchestrates all Telegram Bot API interactions
 *
 * Provides high-level API for handling updates, routing, middleware, and events.
 *
 *     $bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));
 *     $bot->command('start', fn($message, Bot $bot) => $bot->reply($message, 'Hi!'));
 *     $bot->handle(); // webhook, or $bot->poll() for long polling
 */
final class Bot extends ApiClient
{
    private readonly Router $router;
    private readonly MiddlewarePipeline $middleware;
    private readonly EventDispatcher $events;
    private readonly PluginManager $plugins;
    private ?ConversationManager $conversations = null;
    private ?stdClass $update = null;
    private bool $running = false;

    public function __construct(
        string|Config $token,
        bool $debug = false,
        string|false $debugFile = false,
        string|false $secretToken = false,
        ?TransportInterface $transport = null
    ) {
        try {
            $config = $token instanceof Config ? $token : new Config($token, $debug, $debugFile, $secretToken);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidTokenException($e->getMessage());
        }

        parent::__construct($config, $transport);

        $this->router = new Router();
        $this->router->setBot($this);
        $this->middleware = new MiddlewarePipeline();
        $this->events = new EventDispatcher();
        $this->plugins = new PluginManager();
    }

    /**
     * Webhook entry point
     *
     * Reads the request body and the X-Telegram-Bot-Api-Secret-Token header,
     * validates the secret token (when configured) and processes the update.
     * Handler errors are reported to "error" listeners (or the PHP error log)
     * and never cause a non-2xx response, which would make Telegram re-deliver
     * the update over and over.
     *
     * @return bool Whether the request was accepted
     */
    public function handle(?string $body = null, ?string $secretTokenHeader = null): bool
    {
        $secret = $this->config->secretToken;

        if ($secret !== false) {
            $header = $secretTokenHeader ?? WebhookValidator::getSecretToken();

            if (!WebhookValidator::validate('', $secret, $header)) {
                self::respond(403);
                return false;
            }
        }

        $body ??= (string) file_get_contents('php://input');

        try {
            $update = UpdateParser::parse($body);
        } catch (JsonException) {
            self::respond(400);
            return false;
        }

        try {
            $this->processUpdate($update);
        } catch (Throwable $e) {
            error_log('TGbotPHP: unhandled ' . $e::class . ': ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Handle a single update
     *
     * @param string|array<string, mixed>|stdClass $webhookJson Raw JSON, decoded array or object
     *
     * @throws JsonException
     */
    public function handleUpdate(string|array|stdClass $webhookJson): void
    {
        $update = match (true) {
            is_string($webhookJson) => UpdateParser::parse($webhookJson),
            is_array($webhookJson) => UpdateParser::fromArray($webhookJson),
            default => $webhookJson,
        };

        $this->processUpdate($update);
    }

    /**
     * Run middleware and routing for an update
     *
     * Exceptions are passed to "error" listeners when there are any, and re-thrown otherwise.
     */
    public function processUpdate(stdClass $update): void
    {
        $this->update = $update;

        try {
            $this->events->dispatch('update.received', $update);

            $completed = $this->middleware->process(
                $update,
                fn() => $this->route($update),
                [$this]
            );

            if ($completed) {
                $this->events->dispatch('update.processed', $update);
            }
        } catch (Throwable $e) {
            if ($e instanceof ApiException) {
                $this->events->dispatch('error.api', $e, $update);
            }

            if (!$this->events->hasListeners('error')) {
                throw $e;
            }

            $this->events->dispatch('error', $e, $update, $this);
        }
    }

    /**
     * Receive updates with long polling until stop() is called
     *
     * Network and server errors are retried with backoff. A webhook must not
     * be set (see deleteWebhook()).
     *
     * @param string[]|null $allowedUpdates
     * @param int $maxIterations Stop after this many getUpdates calls (0 = run forever)
     */
    public function poll(int $timeout = 30, ?array $allowedUpdates = null, ?int $limit = null, int $maxIterations = 0): void
    {
        if ($this->router->getBotUsername() === null) {
            $me = $this->getMe();
            $this->setUsername(Value::nullableString($me['username'] ?? null));
        }

        $this->running = true;
        $this->events->dispatch('polling.started', $this);

        $offset = null;
        $failures = 0;
        $iterations = 0;

        while ($this->running && ($maxIterations === 0 || $iterations < $maxIterations)) {
            $iterations++;

            try {
                $updates = $this->getUpdates($offset, $limit, $timeout, $allowedUpdates);
                $failures = 0;
            } catch (TooManyRequestsException $e) {
                sleep(max(1, $e->getRetryAfter()));
                continue;
            } catch (NetworkException | ApiException $e) {
                // Unauthorized or conflicting webhook: retrying cannot help
                if ($e instanceof ApiException && in_array($e->getCode(), [401, 404, 409], true)) {
                    $this->running = false;
                    throw $e;
                }

                $this->events->dispatch('error', $e, null, $this);
                sleep(min(30, 2 ** min($failures++, 5)));
                continue;
            }

            foreach ($updates as $data) {
                $updateId = Value::nullableInt($data['update_id'] ?? null);

                if ($updateId === null) {
                    continue;
                }

                $offset = $updateId + 1;

                try {
                    $this->processUpdate(UpdateParser::fromArray($data));
                } catch (Throwable $e) {
                    error_log('TGbotPHP: unhandled ' . $e::class . ': ' . $e->getMessage());
                }

                if (!$this->running) {
                    break;
                }
            }
        }

        // Acknowledge the updates processed in the last batch
        if ($offset !== null) {
            try {
                $this->getUpdates($offset, 1, 0, $allowedUpdates);
            } catch (Throwable) {
                // Best effort: unacknowledged updates will simply be delivered again
            }
        }

        $this->running = false;
        $this->events->dispatch('polling.stopped', $this);
    }

    /**
     * Stop the polling loop after the current update
     */
    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Register command handler: fn(stdClass $message, Bot $bot, string $args)
     */
    public function command(string $command, callable $handler): self
    {
        $this->router->registerCommand($command, $handler);
        return $this;
    }

    /**
     * Register callback query handler: fn(stdClass $callbackQuery, Bot $bot, array $matches)
     *
     * $data may be exact ("menu"), a wildcard ("page:*") or a regex ("/^page:(\d+)$/").
     */
    public function callback(string $data, callable $handler): self
    {
        $this->router->registerCallback($data, $handler);
        return $this;
    }

    /**
     * Register text handler: fn(stdClass $message, Bot $bot, array $matches)
     */
    public function hears(string $pattern, callable $handler): self
    {
        $this->router->registerText($pattern, $handler);
        return $this;
    }

    /**
     * Register inline query handler: fn(stdClass $inlineQuery, Bot $bot, array $matches)
     *
     * Use "*" to match every query.
     */
    public function inlineQuery(string $pattern, callable $handler): self
    {
        $this->router->registerInlineQuery($pattern, $handler);
        return $this;
    }

    /**
     * Register handler for an update type: fn(stdClass $payload, Bot $bot, stdClass $update)
     */
    public function onUpdate(string $type, callable $handler): self
    {
        $this->router->registerUpdate($type, $handler);
        return $this;
    }

    /**
     * Handler for text messages matching no other route: fn(stdClass $message, Bot $bot)
     */
    public function fallback(callable $handler): self
    {
        $this->router->setDefaultHandler($handler);
        return $this;
    }

    /**
     * Handler for unregistered commands: fn(stdClass $message, Bot $bot, string $args)
     */
    public function onUnknownCommand(callable $handler): self
    {
        $this->router->setUnknownCommandHandler($handler);
        return $this;
    }

    /**
     * Handler for messages sent while the conversation is in $state:
     * fn(stdClass $message, Bot $bot, array $stateData)
     *
     * Requires useConversations().
     */
    public function state(string $state, callable $handler): self
    {
        $this->router->registerState($state, $handler);
        return $this;
    }

    /**
     * Register middleware
     */
    public function middleware(callable $handler): self
    {
        $this->middleware->add($handler);
        return $this;
    }

    /**
     * Listen to events
     */
    public function on(string $event, callable $handler): self
    {
        $this->events->listen($event, $handler);
        return $this;
    }

    /**
     * Handle errors thrown while processing updates: fn(Throwable $e, ?stdClass $update, Bot $bot)
     */
    public function onError(callable $handler): self
    {
        return $this->on('error', $handler);
    }

    /**
     * Set the bot username so commands addressed to other bots (/cmd@other_bot) are ignored
     */
    public function setUsername(?string $username): self
    {
        $this->router->setBotUsername($username);
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->router->getBotUsername();
    }

    /**
     * Enable conversation states, stored in the given cache
     */
    public function useConversations(CacheInterface $cache, int $ttl = 3600): self
    {
        $this->conversations = new ConversationManager($cache, $ttl);
        return $this;
    }

    public function conversations(): ConversationManager
    {
        if ($this->conversations === null) {
            throw new \LogicException('Conversations are disabled: call useConversations() first');
        }

        return $this->conversations;
    }

    /**
     * Move the sender of a message into a conversation state
     *
     * @param array<string, mixed> $data
     */
    public function setState(stdClass $message, string $state, array $data = []): void
    {
        [$chatId, $userId] = self::conversationKey($message);
        $this->conversations()->setState($chatId, $userId, $state, $data);
    }

    /**
     * Merge data into the sender's current conversation
     *
     * @param array<string, mixed> $data
     */
    public function updateStateData(stdClass $message, array $data): void
    {
        [$chatId, $userId] = self::conversationKey($message);
        $this->conversations()->updateData($chatId, $userId, $data);
    }

    /**
     * Leave the current conversation state
     */
    public function clearState(stdClass $message): void
    {
        [$chatId, $userId] = self::conversationKey($message);
        $this->conversations()->clear($chatId, $userId);
    }

    /**
     * Reply in the chat (and forum topic) a message came from
     *
     * @param array<string, mixed> $options Extra sendMessage parameters (reply_markup, parse_mode, ...)
     * @return array<string, mixed>
     */
    public function reply(stdClass $message, string $text, array $options = []): array
    {
        [$chatId] = self::conversationKey($message);

        // A callback query carries the original message
        $source = Value::object(Value::path($message, 'message')) ?? $message;
        $threadId = Value::nullableInt(Value::path($source, 'message_thread_id'));

        if (Value::path($source, 'is_topic_message') === true && $threadId !== null) {
            $options += ['message_thread_id' => $threadId];
        }

        // $options override the defaults, including parse_mode and reply_markup
        return $this->sendMessage($chatId, $text, options: $options);
    }

    /**
     * Edit the text of the message a callback query button belongs to
     *
     * Works for regular messages and for messages sent in inline mode.
     *
     * @param array<string, mixed> $options Extra editMessageText parameters (reply_markup, parse_mode, ...)
     * @return array<string, mixed>|bool Edited message, or true for inline messages
     */
    public function edit(stdClass $callbackQuery, string $text, array $options = []): array|bool
    {
        $inlineMessageId = Value::nullableString(Value::path($callbackQuery, 'inline_message_id'));

        if ($inlineMessageId !== null) {
            return $this->editMessageText(null, null, $text, options: ['inline_message_id' => $inlineMessageId] + $options);
        }

        $messageId = Value::nullableInt(Value::path($callbackQuery, 'message', 'message_id'));

        if ($messageId === null) {
            throw new \InvalidArgumentException('The callback query has no message to edit');
        }

        return $this->editMessageText($this->chatId($callbackQuery), $messageId, $text, options: $options);
    }

    /**
     * Chat id of a message, callback query or any payload carrying a chat
     */
    public function chatId(stdClass $payload): int|string
    {
        return self::conversationKey($payload)[0];
    }

    /**
     * Answer a callback query
     */
    public function answer(stdClass $callbackQuery, ?string $text = null, bool $showAlert = false): bool
    {
        $id = Value::nullableString(Value::path($callbackQuery, 'id'));

        if ($id === null) {
            throw new \InvalidArgumentException('The payload is not a callback query');
        }

        return $this->answerCallbackQuery($id, $text, $showAlert);
    }

    /**
     * Register a plugin; plugins implementing BotPluginInterface are booted with this bot
     */
    public function plugin(PluginInterface $plugin): self
    {
        $this->plugins->register($plugin->getName(), $plugin);

        if ($plugin instanceof BotPluginInterface) {
            $plugin->boot($this);
        }

        return $this;
    }

    public function getPlugins(): PluginManager
    {
        return $this->plugins;
    }

    /**
     * Get current update
     */
    public function getUpdate(): ?stdClass
    {
        return $this->update;
    }

    /**
     * Get router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get event dispatcher
     */
    public function getEvents(): EventDispatcher
    {
        return $this->events;
    }

    /**
     * Get middleware pipeline
     */
    public function getMiddleware(): MiddlewarePipeline
    {
        return $this->middleware;
    }

    private function route(stdClass $update): void
    {
        $state = null;
        $stateData = [];

        if ($this->conversations !== null && isset($update->message) && $update->message instanceof stdClass) {
            [$chatId, $userId] = self::conversationKey($update->message);
            $state = $this->conversations->getState($chatId, $userId);
            $stateData = $state !== null ? $this->conversations->getData($chatId, $userId) : [];
        }

        $this->router->dispatch($update, $state, $stateData);
    }

    /**
     * @return array{0: int|string, 1: int|string|null}
     */
    private static function conversationKey(stdClass $message): array
    {
        $chatId = Value::id(Value::path($message, 'chat', 'id') ?? Value::path($message, 'message', 'chat', 'id'));

        if ($chatId === null) {
            throw new \InvalidArgumentException('The payload has no chat');
        }

        return [$chatId, Value::id(Value::path($message, 'from', 'id'))];
    }

    private static function respond(int $statusCode): void
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code($statusCode);
        }
    }
}
