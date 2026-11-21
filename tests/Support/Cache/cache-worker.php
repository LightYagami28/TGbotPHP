<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use TGbotPHP\Cache\FileCache;
use TGbotPHP\Support\Value;

$directory = getenv('TGBOTP_TEST_CACHE_DIR');

if (!is_string($directory) || $directory === '') {
    fwrite(STDERR, "Missing TGBOTP_TEST_CACHE_DIR\n");
    exit(1);
}

$cache = new FileCache($directory);

for ($i = 0; $i < 50; $i++) {
    $cache->update('shared-counter', static fn(mixed $value): int => Value::int($value) + 1);
}
