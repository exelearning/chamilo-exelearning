<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * The page loaded inside the editor iframe: dist/static/index.html with a
 * <base> and the embedding configuration. Only editors of the package, only
 * when the embedded editor is enabled.
 */

use Chamilo\PluginBundle\ExeLearning\Service\EditorBootstrap;

$course_plugin = 'exelearning';

require_once __DIR__.'/config.php';

api_protect_course_script(false);

if (!$plugin->isEnabled() || !$plugin->canEdit() || !$plugin->isEditorEnabled()) {
    api_not_allowed(false);
}

$id = (int) ($_GET['id'] ?? 0);
$projectUrl = '';
if ($id > 0) {
    if (null === $plugin->packageForEdit($id)) {
        api_not_allowed(false);
    }
    $projectUrl = $plugin->url('download.php', ['id' => $id]);
}

$indexHtml = (string) file_get_contents($plugin->staticEditorDir().'/index.html');
$origin = rtrim((string) preg_replace('#^(https?://[^/]+).*$#', '$1', api_get_path(WEB_PATH)), '/');

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: frame-ancestors 'self'");
header('Cache-Control: private, no-store');

echo EditorBootstrap::render(
    $indexHtml,
    api_get_path(WEB_PLUGIN_PATH).'ExeLearning/dist/static',
    $origin,
    $projectUrl,
    (string) api_get_language_isocode(),
    $plugin->assetUrl('resources/js/editor-bridge.js')
);
