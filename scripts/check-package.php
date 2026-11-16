<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * `make check-package VERSION=X.Y.Z`: inspect the release ZIP. It must unpack
 * to a single ExeLearning/ folder, contain the plugin and the built editor,
 * and none of the development-only trees.
 */
$zipPath = $argv[1] ?? '';
$zip = new ZipArchive();
if ('' === $zipPath || true !== $zip->open($zipPath, ZipArchive::RDONLY)) {
    fwrite(STDERR, "Cannot open '$zipPath'.\n");

    exit(1);
}

$names = [];
for ($i = 0; $i < $zip->numFiles; ++$i) {
    $names[] = (string) $zip->getNameIndex($i);
}
$has = static fn (string $path): bool => in_array($path, $names, true);
$any = static fn (string $prefix): bool => [] !== array_filter($names, static fn (string $n): bool => str_starts_with($n, $prefix));

$errors = [];
foreach ($names as $name) {
    if (!str_starts_with($name, 'ExeLearning/')) {
        $errors[] = "Entry outside ExeLearning/: $name";

        break;
    }
}
foreach (['plugin.php', 'install.php', 'uninstall.php', 'start.php', 'view.php', 'content.php', 'edit.php', 'editor.php', 'save.php', 'download.php', 'config.php',
    'src/ExeLearningPlugin.php', 'src/Entity/ExeLearningPackage.php', 'src/EventSubscriber/ExeLearningCleanupEventSubscriber.php',
    'lang/en_US.php', 'lang/es.php', 'resources/js/editor.js', 'resources/js/editor-bridge.js', 'LICENSE', 'README.md',
    'dist/static/index.html', 'dist/static/.editor-version'] as $required) {
    if (!$has('ExeLearning/'.$required)) {
        $errors[] = "Missing: $required";
    }
}
foreach (['.agents/', '.claude/', 'AGENTS.md', 'CLAUDE.md', 'tests/', 'docker/', 'exelearning/', 'vendor/', 'node_modules/', 'scripts/', '.github/', 'docs/', 'Makefile', '.env'] as $forbidden) {
    if ($any('ExeLearning/'.$forbidden)) {
        $errors[] = "Must not ship: $forbidden";
    }
}
foreach (['app/', 'libs/'] as $dir) {
    if (!$any('ExeLearning/dist/static/'.$dir)) {
        $errors[] = "Editor bundle incomplete: dist/static/$dir";
    }
}

if ([] !== $errors) {
    fwrite(STDERR, implode("\n", $errors)."\n");

    exit(1);
}
fwrite(STDOUT, sprintf("%s: %d entries, layout OK.\n", basename($zipPath), count($names)));
