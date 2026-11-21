<?php

declare(strict_types=1);

namespace TGbotPHP\Plugin;

/**
 * Contract every plugin must implement.
 */
interface PluginInterface
{
    /** Unique name of the plugin. */
    public function getName(): string;

    /** Version of the plugin. */
    public function getVersion(): string;

    /** Called once when the plugin is registered. */
    public function activate(): void;

    /** Called once when the plugin is unregistered. */
    public function deactivate(): void;
}
