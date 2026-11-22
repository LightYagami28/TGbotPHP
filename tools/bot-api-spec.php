<?php

/**
 * Extracts the methods of the Telegram Bot API from its documentation page
 *
 *     php tools/bot-api-spec.php [api.html] > tools/bot-api.json
 *
 * Without an argument the page is downloaded from core.telegram.org. The JSON
 * lists the update types, and every method with its parameters and the kind
 * of result it returns; tests/Unit/BotApiCoverageTest.php checks the library
 * against it.
 */

declare(strict_types=1);
require __DIR__ . '/bot-api-spec-lib.php';

$source = $argv[1] ?? API_URL;
$html = @file_get_contents($source);

if ($html === false || $html === '') {
    fwrite(STDERR, "Unable to read $source\n");
    exit(1);
}

// Every array is a map: keep empty ones as objects
echo json_encode(
    extractSpec($html),
    JSON_FORCE_OBJECT | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
), "\n";
