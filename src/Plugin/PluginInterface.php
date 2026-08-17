<?php

declare(strict_types=1);

namespace TGbotPHP\Plugin;

interface PluginInterface
{
    public function getName(): string;

    public function getVersion(): string;

    public function activate(): void;

    public function deactivate(): void;
}
