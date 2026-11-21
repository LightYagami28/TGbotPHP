<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Routing;

/**
 * A matched route: the handler and the arguments that follow the payload and the bot.
 */
final readonly class Route
{
    /** @var callable Handler to invoke */
    public mixed $handler;

    /**
     * @param callable $handler Handler to invoke
     * @param list<mixed> $arguments Extra arguments (captured groups)
     */
    public function __construct(callable $handler, public array $arguments = [])
    {
        $this->handler = $handler;
    }
}
