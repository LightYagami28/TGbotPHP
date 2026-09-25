<?php

declare(strict_types=1);

namespace TGbotPHP\Framework;

use stdClass;
use TGbotPHP\Exceptions\ApiException;
use TGbotPHP\Session\ConversationManager;
use TGbotPHP\Support\Payload;
use Throwable;

/**
 * Processes one update: events, middleware, conversation state and routing
 */
final class Kernel
{
    public readonly Router $router;
    public readonly MiddlewarePipeline $middleware;
    public readonly EventDispatcher $events;

    public private(set) ?ConversationManager $conversations = null;

    /** The update being processed, or the last one */
    public private(set) ?stdClass $update = null;

    public function __construct()
    {
        $this->router = new Router();
        $this->middleware = new MiddlewarePipeline();
        $this->events = new EventDispatcher();
    }

    public function useConversations(ConversationManager $conversations): void
    {
        $this->conversations = $conversations;
    }

    /**
     * Exceptions go to "error" listeners when there are any, and are re-thrown otherwise
     */
    public function process(stdClass $update, Bot $bot): void
    {
        $this->update = $update;

        try {
            $this->events->dispatch('update.received', $update);

            if ($this->middleware->process($update, fn() => $this->route($update), [$bot])) {
                $this->events->dispatch('update.processed', $update);
            }
        } catch (Throwable $e) {
            $this->report($e, $update, $bot);
        }
    }

    private function route(stdClass $update): void
    {
        $message = Payload::message($update);
        [$state, $data] = $message !== null && $this->conversations !== null
            ? $this->conversations->stateOf($message)
            : [null, []];

        $this->router->dispatch($update, $state, $data);
    }

    private function report(Throwable $e, stdClass $update, Bot $bot): void
    {
        if ($e instanceof ApiException) {
            $this->events->dispatch('error.api', $e, $update);
        }

        if (!$this->events->hasListeners('error')) {
            throw $e;
        }

        $this->events->dispatch('error', $e, $update, $bot);
    }
}
