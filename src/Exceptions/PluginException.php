<?php

declare(strict_types=1);

namespace TGbotPHP\Exceptions;

/**
 * Raised when a plugin cannot be registered with the bot.
 *
 * Typical causes are an invalid plugin class, a duplicate name or a
 * registration callback that rejected the plugin.
 */
class PluginException extends TelegramException
{
}
