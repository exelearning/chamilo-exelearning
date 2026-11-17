<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * `make check-version [VERSION=X.Y.Z]`.
 *
 * As in the sibling integrations, the source tree carries version 0.0.0 and
 * `make package VERSION=...` stamps the release version into the packaged copy
 * only. This checks that the placeholder is intact and, when given, that
 * VERSION is X.Y.Z or X.Y.Z-(alpha|beta|rc).N.
 */
$source = (string) file_get_contents(dirname(__DIR__).'/src/ExeLearningPlugin.php');
if (1 !== preg_match("/public const string VERSION = '([^']+)';/", $source, $m)) {
    fwrite(\STDERR, "ExeLearningPlugin::VERSION not found.\n");

    exit(1);
}
if ('0.0.0' !== $m[1]) {
    fwrite(\STDERR, "ExeLearningPlugin::VERSION is '{$m[1]}'; the source must keep 0.0.0 (make package stamps the release version).\n");

    exit(1);
}

$version = ltrim($argv[1] ?? '', 'v');
if ('' !== $version && 1 !== preg_match('/^\d+\.\d+\.\d+(-(alpha|beta|rc)\.?\d+)?$/', $version)) {
    fwrite(\STDERR, "VERSION '$version' must be X.Y.Z or X.Y.Z-(alpha|beta|rc).N.\n");

    exit(1);
}

fwrite(\STDOUT, '' === $version ? "Source version placeholder OK.\n" : "Version $version OK.\n");
