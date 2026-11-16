<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);
use Chamilo\PluginBundle\ExeLearning\Service\ContentToken;

/*
 * Package viewer: the package runs in a sandboxed iframe served only through
 * content.php, with a short-lived token minted after the course access check.
 */
$course_plugin = 'exelearning';

require_once __DIR__.'/config.php';

api_protect_course_script(true);

if (!$plugin->isEnabled()) {
    api_not_allowed(true);
}

$package = $plugin->packageForView((int) ($_GET['id'] ?? 0));
if (null === $package) {
    api_not_allowed(true);
}

$e = static fn ($value): string => htmlspecialchars((string) $value, \ENT_QUOTES, 'UTF-8');
$id = (int) $package->getId();
$hash = $package->getContentHash();
$token = $plugin->contentToken()->issue(api_get_user_id(), $id, $hash, time() + ContentToken::DEFAULT_TTL);
$contentUrl = api_get_path(WEB_PLUGIN_PATH).'ExeLearning/content.php/'.$id.'/'.$hash.'/'.$token.'/index.html';

$actions = '<a class="btn btn--plain" href="'.$e($plugin->url('start.php')).'">'.$plugin->e('back_to_list').'</a>'
    .' <button type="button" class="btn btn--plain" data-exelearning-fullscreen>'.$plugin->e('fullscreen').'</button>';
if (null !== $plugin->packageForEdit($id) && $plugin->isEditorEnabled()) {
    $actions .= ' <a class="btn btn--primary" href="'.$e($plugin->url('edit.php', ['id' => $id])).'">'.$plugin->e('edit').'</a>';
}

$html = '<link rel="stylesheet" href="'.$e($plugin->assetUrl('resources/css/exelearning.css')).'">'
    .'<div class="exelearning-viewer" data-exelearning-viewer>'
    .'<div class="exelearning-toolbar">'.$actions.'</div>'
    .'<iframe class="exelearning-frame" title="'.$e($package->getTitle()).'" src="'.$e($contentUrl).'"'
    .' sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-forms allow-downloads"'
    .' allow="fullscreen" allowfullscreen referrerpolicy="no-referrer"></iframe>'
    .'</div>'
    .'<script src="'.$e($plugin->assetUrl('resources/js/viewer.js')).'"></script>';

$view = new Template($package->getTitle());
$view->assign('header', $package->getTitle());
$view->assign('content', $html);
$view->display_one_col_template();
