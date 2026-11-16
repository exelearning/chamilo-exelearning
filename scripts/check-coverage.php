<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * `make test-coverage`: fail when line coverage in a Clover report is below
 * the MIN_COVERAGE ratchet. Raise MIN_COVERAGE as coverage grows; never lower it.
 */
[, $file, $minimum] = $argv + [null, 'coverage.xml', '0'];
$xml = @simplexml_load_file((string) $file);
if (false === $xml) {
    fwrite(STDERR, "Cannot read $file.\n");

    exit(1);
}
$metrics = $xml->project->metrics;
$total = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = 0 === $total ? 100.0 : 100 * $covered / $total;
printf("Line coverage: %.2f%% (%d/%d), minimum %s%%\n", $percent, $covered, $total, $minimum);
exit($percent + 1e-9 < (float) $minimum ? 1 : 0);
