<?php

declare(strict_types=1);

namespace TGbotPHP\Utilities;

use TGbotPHP\Cache\CacheInterface;
use TGbotPHP\Core\Config;
use TGbotPHP\Core\RetryPolicy;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Http\TransportInterface;
use TGbotPHP\Plugin\PluginInterface;

/**
 * Fluent bot factory
 *
 *     $bot = (new BotBuilder(getenv('TELEGRAM_BOT_TOKEN')))
 *         ->withSecretToken(getenv('TELEGRAM_SECRET_TOKEN'))
 *         ->addCommand('start', fn($message, Bot $bot) => $bot->reply($message, 'Welcome!'))
 *         ->build();
 */
class BotBuilder
{
    private string $token;
    private bool|string $debug = false;
    private string|false $secretToken = false;
    private ?string $username = null;
    private string $apiBaseUrl = Config::DEFAULT_API_URL;
    private bool $enforceHttps = true;
    private int $timeout = 10;
    private RetryPolicy $retry;
    private ?TransportInterface $transport = null;
    private ?CacheInterface $conversationCache = null;
    private int $conversationTtl = 3600;

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $commands = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $callbacks = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $texts = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $inlineQueries = [];

    /** @var array<int|string, callable> Numeric-string keys become integers */
    private array $states = [];

    /** @var array<int, array{0: string, 1: callable}> */
    private array $updates = [];

    /** @var callable|null */
    private $fallback = null;

    /** @var callable[] */
    private array $middleware = [];

    /** @var array<string, callable[]> */
    private array $events = [];

    /** @var PluginInterface[] */
    private array $plugins = [];

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->retry = new RetryPolicy();
    }

    /**
     * Log every request and response: true for the PHP error log, or a file path
     *
     * The log contains message contents: keep it private.
     */
    public function withDebug(bool|string $log = true): self
    {
        $this->debug = $log;
        return $this;
    }

    public function withSecretToken(string $token): self
    {
        $this->secretToken = $token;
        return $this;
    }

    public function withUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Use a local Bot API server (https is not required when $enforceHttps is false)
     */
    public function withApiServer(string $baseUrl, bool $enforceHttps = true): self
    {
        $this->apiBaseUrl = $baseUrl;
        $this->enforceHttps = $enforceHttps;
        return $this;
    }

    public function withTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * How requests answered with 429 Too Many Requests are retried
     */
    public function withRetry(RetryPolicy $retry): self
    {
        $this->retry = $retry;
        return $this;
    }

    public function withTransport(TransportInterface $transport): self
    {
        $this->transport = $transport;
        return $this;
    }

    public function withConversations(CacheInterface $cache, int $ttl = 3600): self
    {
        $this->conversationCache = $cache;
        $this->conversationTtl = $ttl;
        return $this;
    }

    public function addCommand(string $command, callable $handler): self
    {
        $this->commands[$command] = $handler;
        return $this;
    }

    public function addCallback(string $data, callable $handler): self
    {
        $this->callbacks[$data] = $handler;
        return $this;
    }

    public function addText(string $pattern, callable $handler): self
    {
        $this->texts[$pattern] = $handler;
        return $this;
    }

    public function addInlineQuery(string $pattern, callable $handler): self
    {
        $this->inlineQueries[$pattern] = $handler;
        return $this;
    }

    public function addState(string $state, callable $handler): self
    {
        $this->states[$state] = $handler;
        return $this;
    }

    public function addUpdateHandler(string $type, callable $handler): self
    {
        $this->updates[] = [$type, $handler];
        return $this;
    }

    public function setFallback(callable $handler): self
    {
        $this->fallback = $handler;
        return $this;
    }

    public function addMiddleware(callable $handler): self
    {
        $this->middleware[] = $handler;
        return $this;
    }

    public function addEventListener(string $event, callable $handler): self
    {
        $this->events[$event][] = $handler;
        return $this;
    }

    public function addPlugin(PluginInterface $plugin): self
    {
        $this->plugins[] = $plugin;
        return $this;
    }

    public function build(): Bot
    {
        $config = new Config(
            token: $this->token,
            secretToken: $this->secretToken,
            apiBaseUrl: $this->apiBaseUrl,
            enforceHttps: $this->enforceHttps,
            timeout: $this->timeout,
            retry: $this->retry,
            debug: $this->debug,
        );

        $bot = new Bot($config, $this->transport);

        if ($this->username !== null) {
            $bot->setUsername($this->username);
        }

        if ($this->conversationCache !== null) {
            $bot->useConversations($this->conversationCache, $this->conversationTtl);
        }

        foreach ($this->commands as $command => $handler) {
            $bot->command((string) $command, $handler);
        }

        foreach ($this->callbacks as $data => $handler) {
            $bot->callback((string) $data, $handler);
        }

        foreach ($this->texts as $pattern => $handler) {
            $bot->hears((string) $pattern, $handler);
        }

        foreach ($this->inlineQueries as $pattern => $handler) {
            $bot->inlineQuery((string) $pattern, $handler);
        }

        foreach ($this->states as $state => $handler) {
            $bot->state((string) $state, $handler);
        }

        foreach ($this->updates as [$type, $handler]) {
            $bot->onUpdate($type, $handler);
        }

        if ($this->fallback !== null) {
            $bot->fallback($this->fallback);
        }

        foreach ($this->middleware as $handler) {
            $bot->middleware($handler);
        }

        foreach ($this->events as $event => $handlers) {
            foreach ($handlers as $handler) {
                $bot->on($event, $handler);
            }
        }

        foreach ($this->plugins as $plugin) {
            $bot->plugin($plugin);
        }

        return $bot;
    }
}
