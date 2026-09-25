<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

use JsonException;
use stdClass;
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Core\Config;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Exceptions\InvalidTokenException;
use TGbotPHP\Framework\Concerns\ManagesConversations;
use TGbotPHP\Framework\Concerns\RegistersHandlers;
use TGbotPHP\Framework\Concerns\RespondsToUpdates;
use TGbotPHP\Framework\Runner\LongPolling;
use TGbotPHP\Framework\Runner\WebhookHandler;
use TGbotPHP\Http\TransportInterface;
use TGbotPHP\Plugin\BotPluginInterface;
use TGbotPHP\Plugin\PluginInterface;
use TGbotPHP\Plugin\PluginManager;
use TGbotPHP\Support\Value;

/**
 * Telegram bot: the API client plus routing, middleware, events and runners
 *
 *     $bot = new Bot(getenv('TELEGRAM_BOT_TOKEN'));
 *     $bot->command('start', fn(stdClass $message, Bot $bot) => $bot->reply($message, 'Hi!'));
 *     $bot->handle(); // webhook, or $bot->poll() for long polling
 */
final class Bot extends ApiClient
{
    use RegistersHandlers;
    use RespondsToUpdates;
    use ManagesConversations;

    private readonly Kernel $kernel;
    private readonly PluginManager $plugins;
    private ?LongPolling $polling = null;

    public function __construct(string|Config $token, ?TransportInterface $transport = null)
    {
        try {
            $config = $token instanceof Config ? $token : new Config($token);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidTokenException($e->getMessage());
        }

        parent::__construct($config, $transport);

        $this->kernel = new Kernel();
        $this->kernel->router->setBot($this);
        $this->plugins = new PluginManager();
    }

    /**
     * Webhook entry point: see WebhookHandler
     *
     * @return bool Whether the update was accepted
     */
    public function handle(?string $body = null, ?string $secretTokenHeader = null): bool
    {
        return new WebhookHandler($this)->handle($body, $secretTokenHeader);
    }

    /**
     * Process one update given as JSON, a decoded array or an object
     *
     * @param string|array<string, mixed>|stdClass $update
     *
     * @throws JsonException
     */
    public function handleUpdate(string|array|stdClass $update): void
    {
        $this->processUpdate(match (true) {
            is_string($update) => UpdateParser::parse($update),
            is_array($update) => UpdateParser::fromArray($update),
            default => $update,
        });
    }

    /**
     * Run middleware and routing; exceptions go to onError() handlers, or are re-thrown
     */
    public function processUpdate(stdClass $update): void
    {
        $this->kernel->process($update, $this);
    }

    /**
     * Long polling until stop() is called: see LongPolling
     *
     * @param string[]|null $allowedUpdates
     * @param int $maxIterations Stop after this many getUpdates calls (0: run until stop())
     */
    public function poll(int $timeout = 30, ?array $allowedUpdates = null, ?int $limit = null, int $maxIterations = 0): void
    {
        if ($this->getUsername() === null) {
            $this->setUsername(Value::nullableString($this->getMe()['username'] ?? null));
        }

        $this->polling = new LongPolling($this);
        $this->polling->run($timeout, $allowedUpdates, $limit, $maxIterations);
    }

    /**
     * Stop polling after the update being processed
     */
    public function stop(): void
    {
        $this->polling?->stop();
    }

    public function isRunning(): bool
    {
        return $this->polling !== null && $this->polling->running;
    }

    /**
     * Commands addressed to other bots (/start@other_bot) are ignored once the username is set
     */
    public function setUsername(?string $username): static
    {
        $this->kernel->router->setBotUsername($username);
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->kernel->router->getBotUsername();
    }

    /**
     * Plugins implementing BotPluginInterface are booted with this bot
     */
    public function plugin(PluginInterface $plugin): static
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
     * The update being processed, or the last one
     */
    public function getUpdate(): ?stdClass
    {
        return $this->kernel->update;
    }

    public function getRouter(): Router
    {
        return $this->kernel->router;
    }

    public function getEvents(): EventDispatcher
    {
        return $this->kernel->events;
    }

    public function getMiddleware(): MiddlewarePipeline
    {
        return $this->kernel->middleware;
    }

    #[\Override]
    protected function kernel(): Kernel
    {
        return $this->kernel;
    }
}
