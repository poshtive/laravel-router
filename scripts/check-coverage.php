<?php

$file = $argv[1] ?? 'coverage.xml';
$minimum = (float) ($argv[2] ?? 100);

if (! is_file($file)) {
    fwrite(STDERR, "Coverage report not found: {$file}\n");
    exit(1);
}

$coverage = simplexml_load_file($file);
$metrics = $coverage?->project?->metrics;
$statements = (int) ($metrics['statements'] ?? 0);
$covered = (int) ($metrics['coveredstatements'] ?? 0);
$percent = $statements === 0 ? 0 : ($covered / $statements) * 100;

printf("Line coverage: %.2f%% (minimum %.2f%%)\n", $percent, $minimum);

if ($percent + PHP_FLOAT_EPSILON < $minimum) {
    exit(1);
}
