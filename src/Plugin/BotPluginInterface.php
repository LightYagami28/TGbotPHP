<?php

declare(strict_types=1);

namespace TGbotPHP\Plugin;

use TGbotPHP\Framework\Bot;

/**
 * Plugin that registers handlers, middleware or listeners on a bot
 *
 *     final class PingPlugin implements BotPluginInterface
 *     {
 *         public function boot(Bot $bot): void
 *         {
 *             $bot->command('ping', fn($message, Bot $bot) => $bot->reply($message, 'pong'));
 *         }
 *         // getName(), getVersion(), activate(), deactivate() ...
 *     }
 *
 *     $bot->plugin(new PingPlugin());
 */
interface BotPluginInterface extends PluginInterface
{
    public function boot(Bot $bot): void;
}
