<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Support;

/**
 * Flags for the namespaced stand-ins of flock(), curl_init() and function_exists().
 * Production code calls the real functions; these stand-ins exist only under PHPUnit.
 */
final class RuntimeControls
{
    public static bool $failFlock = false;

    public static bool $failCurlInit = false;

    public static bool $hideSodium = false;

    /** null: ask PHP. Otherwise the forced result of function_exists() for that SAPI function. */
    public static ?bool $fastcgi = null;

    public static ?bool $litespeed = null;

    /** @var list<string> */
    public static array $finished = [];

    public static function reset(): void
    {
        self::$failFlock = false;
        self::$failCurlInit = false;
        self::$hideSodium = false;
        self::$fastcgi = null;
        self::$litespeed = null;
        self::$finished = [];
    }
}

namespace TGbotPHP\Cache;

function flock(mixed $stream, int $operation, ?int &$wouldBlock = null): bool
{
    if (!is_resource($stream)) {
        return false;
    }

    $mode = $operation & 7;

    if (\TGbotPHP\Tests\Support\RuntimeControls::$failFlock && ($mode & LOCK_EX) === LOCK_EX) {
        return false;
    }

    return \flock($stream, $mode, $wouldBlock);
}

namespace TGbotPHP\Http;

function curl_init(?string $url = null): \CurlHandle|false
{
    if (\TGbotPHP\Tests\Support\RuntimeControls::$failCurlInit) {
        return false;
    }

    return \curl_init($url);
}

namespace TGbotPHP\Security;

function function_exists(string $function): bool
{
    if ($function === 'sodium_crypto_sign_verify_detached' && \TGbotPHP\Tests\Support\RuntimeControls::$hideSodium) {
        return false;
    }

    return \function_exists($function);
}

namespace TGbotPHP\Framework\Runner;

function function_exists(string $function): bool
{
    $controls = \TGbotPHP\Tests\Support\RuntimeControls::class;

    if ($function === 'fastcgi_finish_request' && $controls::$fastcgi !== null) {
        return $controls::$fastcgi;
    }

    if ($function === 'litespeed_finish_request' && $controls::$litespeed !== null) {
        return $controls::$litespeed;
    }

    return \function_exists($function);
}

function fastcgi_finish_request(): bool
{
    \TGbotPHP\Tests\Support\RuntimeControls::$finished[] = 'fastcgi';

    return true;
}

function litespeed_finish_request(): bool
{
    \TGbotPHP\Tests\Support\RuntimeControls::$finished[] = 'litespeed';

    return true;
}
