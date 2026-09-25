<?php

declare(strict_types=1);

/**
 * Fails when line coverage falls below a minimum
 *
 *     php tools/coverage-check.php coverage.xml 95
 */

[, $file, $minimum] = $argv + [1 => 'coverage.xml', 2 => '90'];

$xml = @simplexml_load_file($file);
$metrics = $xml !== false ? $xml->project?->metrics : null;

if ($metrics === null) {
    fwrite(STDERR, "No Clover report in $file\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$coverage = $statements > 0 ? 100 * $covered / $statements : 0.0;

printf("Line coverage: %.2f%% (%d/%d), minimum %s%%\n", $coverage, $covered, $statements, $minimum);

exit($coverage < (float) $minimum ? 1 : 0);
