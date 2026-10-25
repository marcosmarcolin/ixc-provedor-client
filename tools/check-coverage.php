<?php

/**
 * Fails when the line coverage of a Clover report is below a minimum.
 *
 * Usage: php tools/check-coverage.php build/coverage.xml 95
 * PHPUnit 9, the last to run on PHP 7.4, has no minimum coverage option of its own.
 */

declare(strict_types=1);

$file = $argv[1] ?? '';
$minimum = (float) ($argv[2] ?? '0');
$xml = is_file($file) ? simplexml_load_file($file) : false;
if ($xml === false) {
    fwrite(STDERR, "Clover report not found or invalid: {$file}\n");
    exit(1);
}

$metrics = $xml->project->metrics;
$total = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$coverage = $total === 0 ? 100.0 : $covered / $total * 100;
$summary = sprintf('Line coverage: %.2f%% (%d/%d), minimum %.2f%%', $coverage, $covered, $total, $minimum);

if ($coverage < $minimum) {
    fwrite(STDERR, $summary . "\n");
    exit(1);
}

fwrite(STDOUT, $summary . "\n");
