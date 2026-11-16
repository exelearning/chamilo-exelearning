<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Editor host page: a full-window page with the embedded editor iframe and the
 * bridge (resources/js/editor.js) that asks it for the .elpx and posts it to
 * save.php. Without ?id it creates a new package.
 */
$course_plugin = 'exelearning';

require_once __DIR__.'/config.php';

api_protect_course_script(true);

if (!$plugin->isEnabled() || !$plugin->canEdit()) {
    api_not_allowed(true);
}

$id = (int) ($_GET['id'] ?? 0);
$package = $id > 0 ? $plugin->packageForEdit($id) : null;
if ($id > 0 && null === $package) {
    api_not_allowed(true);
}

$e = static fn ($value): string => htmlspecialchars((string) $value, \ENT_QUOTES, 'UTF-8');

if (!$plugin->isEditorEnabled()) {
    Display::addFlash(Display::return_message($plugin->get_lang('editor_disabled'), 'warning'));
    header('Location: '.$plugin->url('start.php'));

    exit;
}

$config = [
    'origin' => rtrim((string) preg_replace('#^(https?://[^/]+).*$#', '$1', api_get_path(WEB_PATH)), '/'),
    'saveUrl' => $plugin->url('save.php'),
    'returnUrl' => $plugin->url('start.php'),
    'viewUrl' => $plugin->url('view.php'),
    'packageId' => $id,
    'expectedHash' => $package?->getContentHash() ?? '',
    'tokenName' => ExeLearningPlugin::CSRF_PREFIX.'_sec_token',
    'token' => Security::get_existing_token(ExeLearningPlugin::CSRF_PREFIX),
    'i18n' => [
        'saving' => $plugin->get_lang('saving'),
        'saved' => $plugin->get_lang('package_saved'),
        'saveFailed' => $plugin->get_lang('save_failed'),
        'conflict' => $plugin->get_lang('save_conflict'),
        'unsaved' => $plugin->get_lang('unsaved_changes'),
        'loading' => $plugin->get_lang('editor_loading'),
    ],
];
$editorUrl = $plugin->url('editor.php', $id > 0 ? ['id' => $id] : []);
$title = $package?->getTitle() ?? $plugin->get_lang('new_package');
$lang = api_get_language_isocode();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="<?php echo $e($lang); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $e($title); ?> · <?php echo $plugin->e('plugin_title'); ?></title>
<link rel="stylesheet" href="<?php echo $e($plugin->assetUrl('resources/css/exelearning.css')); ?>">
</head>
<body class="exelearning-editor-page">
<div class="exelearning-editor" data-exelearning-editor data-config="<?php echo $e(json_encode($config, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)); ?>">
    <header class="exelearning-editor-bar">
        <?php if (null === $package) { ?>
            <label class="exelearning-editor-title"><?php echo $plugin->e('title'); ?>
                <input type="text" name="title" maxlength="255" data-exelearning-title value="">
            </label>
        <?php } else { ?>
            <strong class="exelearning-editor-title"><?php echo $e($title); ?></strong>
        <?php } ?>
        <span class="exelearning-editor-status" role="status" aria-live="polite" data-exelearning-status><?php echo $plugin->e('editor_loading'); ?></span>
        <button type="button" class="btn btn--primary" data-exelearning-save disabled><?php echo $plugin->e('save'); ?></button>
        <button type="button" class="btn btn--plain" data-exelearning-close><?php echo $plugin->e('close'); ?></button>
    </header>
    <iframe class="exelearning-editor-frame" title="<?php echo $plugin->e('plugin_title'); ?>" src="<?php echo $e($editorUrl); ?>" allow="clipboard-read; clipboard-write; fullscreen"></iframe>
</div>
<script type="module" src="<?php echo $e($plugin->assetUrl('resources/js/editor.js')); ?>"></script>
</body>
</html>
