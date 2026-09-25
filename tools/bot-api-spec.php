<?php

declare(strict_types=1);

/**
 * Extracts the methods of the Telegram Bot API from its documentation page
 *
 *     php tools/bot-api-spec.php [api.html] > tools/bot-api.json
 *
 * Without an argument the page is downloaded from core.telegram.org. The JSON
 * lists every method with its parameters and the kind of result it returns;
 * tests/Unit/BotApiCoverageTest.php checks the library against it.
 */

const API_URL = 'https://core.telegram.org/bots/api';

/**
 * @return array{version: string, methods: array<string, array{returns: string, parameters: array<string, array{type: string, required: bool}>}>}
 */
function extractSpec(string $html): array
{
    $document = new DOMDocument();
    @$document->loadHTML($html, LIBXML_NOERROR);

    $methods = [];

    foreach ($document->getElementsByTagName('h4') as $heading) {
        $name = trim($heading->textContent);

        // Methods are lowerCamelCase, types are UpperCamelCase
        if (preg_match('/^[a-z][A-Za-z]+$/', $name) !== 1) {
            continue;
        }

        $description = '';
        $parameters = [];

        for ($node = $heading->nextElementSibling; $node !== null && !in_array($node->nodeName, ['h3', 'h4'], true); $node = $node->nextElementSibling) {
            if ($node->nodeName === 'p') {
                $description .= ' ' . $node->textContent;
            } elseif ($node->nodeName === 'table') {
                $parameters = extractParameters($node);
            }
        }

        $methods[$name] = ['returns' => returnKind($description), 'parameters' => $parameters];
    }

    ksort($methods);

    return ['version' => extractVersion($html), 'methods' => $methods];
}

/**
 * @return array<string, array{type: string, required: bool}>
 */
function extractParameters(DOMElement $table): array
{
    $parameters = [];

    foreach ($table->getElementsByTagName('tr') as $row) {
        $cells = $row->getElementsByTagName('td');

        if ($cells->length < 3) {
            continue;
        }

        $parameters[trim((string) $cells->item(0)?->textContent)] = [
            'type' => trim((string) $cells->item(1)?->textContent),
            'required' => trim((string) $cells->item(2)?->textContent) === 'Yes',
        ];
    }

    return $parameters;
}

/**
 * Kind of result, read from the sentences of the description that mention it
 */
function returnKind(string $description): string
{
    $sentences = preg_split('/(?<=\.)\s+/', trim($description));
    $returns = '';

    foreach ($sentences === false ? [] : $sentences as $sentence) {
        if (preg_match('/\breturn(s|ed)?\b/i', $sentence) === 1) {
            $returns .= ' ' . $sentence;
        }
    }

    return match (true) {
        preg_match('/otherwise True/i', $returns) === 1 => 'object|true',
        preg_match('/\bArray of\b/', $returns) === 1 => 'list',
        preg_match('/Returns True\b|True is returned/i', $returns) === 1 => 'bool',
        preg_match('/\bInt\b|\binteger\b/i', $returns) === 1 => 'int',
        preg_match('/\bString\b|as a string|link as String|string on success/i', $returns) === 1 => 'string',
        default => 'object',
    };
}

function extractVersion(string $html): string
{
    return preg_match('/Bot API (\d+\.\d+)/', $html, $match) === 1 ? $match[1] : 'unknown';
}

$source = $argv[1] ?? API_URL;
$html = @file_get_contents($source);

if ($html === false || $html === '') {
    fwrite(STDERR, "Unable to read $source\n");
    exit(1);
}

// Every array is a map: keep empty ones as objects
echo json_encode(extractSpec($html), JSON_FORCE_OBJECT | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
