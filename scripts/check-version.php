<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * `make check-version [VERSION=X.Y.Z]`: the version lives in
 * ExeLearningPlugin::VERSION (plugin.php reads it through get_info()) and must
 * have a CHANGELOG.md entry. With an argument, it must also equal it.
 * `--print` only prints the version.
 */
$root = dirname(__DIR__);
$source = (string) file_get_contents($root.'/src/ExeLearningPlugin.php');
if (1 !== preg_match("/public const string VERSION = '([^']+)';/", $source, $m)) {
    fwrite(\STDERR, "ExeLearningPlugin::VERSION not found.\n");

    exit(1);
}
$version = $m[1];
if ('--print' === ($argv[1] ?? '')) {
    echo $version;

    exit(0);
}
$errors = [];

if (1 !== preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    $errors[] = "VERSION '$version' is not X.Y.Z.";
}
$expected = $argv[1] ?? '';
if ('' !== $expected && ltrim($expected, 'v') !== $version) {
    $errors[] = "VERSION is '$version' but '$expected' was requested.";
}
if (!str_contains((string) @file_get_contents($root.'/CHANGELOG.md'), '## ['.$version.']')) {
    $errors[] = "CHANGELOG.md has no '## [$version]' section.";
}

if ([] !== $errors) {
    fwrite(\STDERR, implode("\n", $errors)."\n");

    exit(1);
}
fwrite(\STDOUT, "Version $version is consistent.\n");
