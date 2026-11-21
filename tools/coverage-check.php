<?php

/**
 * Fails when line coverage falls below a minimum
 *
 *     php tools/coverage-check.php coverage.xml 100
 */

declare(strict_types=1);

$arguments = $_SERVER['argv'] ?? [];
if (!is_array($arguments)) {
    $arguments = [];
}
$rawFile = isset($arguments[1]) && is_string($arguments[1]) ? $arguments[1] : 'coverage.xml';
$minimum = isset($arguments[2]) && is_string($arguments[2]) ? $arguments[2] : '90';

// Accetta solo un file locale dentro la directory di lavoro: un URL verrebbe
// risolto da simplexml_load_file come richiesta di rete (SSRF).
$cwd = getcwd();
$root = __DIR__;
if (is_string($cwd)) {
    $resolvedRoot = realpath($cwd);
    if ($resolvedRoot !== false) {
        $root = $resolvedRoot;
    }
}
$file = realpath($rawFile);
if ($file === false || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "No local Clover report in $rawFile\n");
    exit(1);
}
$contents = file_get_contents($file);
if ($contents === false) {
    fwrite(STDERR, "Unable to read Clover report in $file\n");
    exit(1);
}

$xml = @simplexml_load_string($contents);
$project = $xml === false ? null : $xml->project;
$metrics = $project?->metrics;

if ($project === null || $metrics === null) {
    fwrite(STDERR, "No Clover report in $file\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$coverage = $statements > 0 ? 100 * $covered / $statements : 0.0;

printf("Line coverage: %.2f%% (%d/%d), minimum %s%%\n", $coverage, $covered, $statements, $minimum);

if ($coverage >= (float) $minimum) {
    exit(0);
}

fwrite(STDERR, "Uncovered executable lines:\n");

$files = $project->xpath('.//file');

foreach ($files === null ? [] : $files as $sourceFile) {
    $name = (string) $sourceFile['name'];

    $lines = $sourceFile->line;

    foreach ($lines === null ? [] : $lines as $line) {
        if ((int) $line['count'] === 0 && (string) $line['type'] === 'stmt') {
            fwrite(STDERR, "  $name:" . (string) $line['num'] . "\n");
        }
    }
}

exit(1);
