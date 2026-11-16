<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);
use Chamilo\PluginBundle\ExeLearning\Service\PackageException;

/*
 * `make check-untranslated`: every lang/*.php has exactly the keys of
 * lang/en_US.php, no empty value, and every key used in the code exists.
 */
$root = dirname(__DIR__);
$load = static function (string $file): array {
    $strings = [];

    include $file;

    return $strings;
};
$base = $load($root.'/lang/en_US.php');
$errors = [];

foreach (glob($root.'/lang/*.php') ?: [] as $file) {
    $lang = basename($file, '.php');
    $strings = $load($file);
    foreach (array_diff_key($base, $strings) as $key => $_) {
        $errors[] = "$lang: missing '$key'";
    }
    foreach (array_diff_key($strings, $base) as $key => $_) {
        $errors[] = "$lang: unknown '$key'";
    }
    foreach ($strings as $key => $value) {
        if ('' === trim((string) $value)) {
            $errors[] = "$lang: empty '$key'";
        }
    }
}

$code = '';
foreach (array_merge(glob($root.'/*.php') ?: [], glob($root.'/src/*.php') ?: [], glob($root.'/src/*/*.php') ?: []) as $file) {
    $code .= file_get_contents($file);
}
preg_match_all("/(?:get_lang|->e)\\('([a-z0-9_]+)'\\)/", $code, $used);
preg_match_all('/PackageException::([A-Z_]+)/', $code, $errorConstants);
foreach (array_unique($used[1]) as $key) {
    if (!isset($base[$key])) {
        $errors[] = "en_US: '$key' is used in the code but not defined";
    }
}

require_once $root.'/src/Service/PackageException.php';
$reflection = new ReflectionClass(PackageException::class);
foreach ($reflection->getConstants() as $value) {
    if (!isset($base[$value])) {
        $errors[] = "en_US: error key '$value' is not defined";
    }
}

if ([] !== $errors) {
    fwrite(\STDERR, implode("\n", $errors)."\n");

    exit(1);
}
fwrite(\STDOUT, sprintf("%d keys, %d languages, all translated.\n", count($base), count(glob($root.'/lang/*.php') ?: [])));
