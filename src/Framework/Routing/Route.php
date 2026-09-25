<?php

declare(strict_types=1);

namespace TGbotPHP\Framework\Routing;

/**
 * A matched route: the handler and the arguments that follow the payload and the bot
 */
final readonly class Route
{
    /** @var callable */
    public mixed $handler;

    /**
     * @param list<mixed> $arguments
     */
    public function __construct(callable $handler, public array $arguments = [])
    {
        $this->handler = $handler;
    }
}
