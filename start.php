<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Course tool home: list, upload, rename, show/hide, delete packages.
 *
 * GET renders; every change is a POST with the plugin CSRF token, a capability
 * check and an ownership check on the specific package (Chamilo rule 13).
 */

use Chamilo\PluginBundle\ExeLearning\Entity\ExeLearningPackage;
use Chamilo\PluginBundle\ExeLearning\Service\AccessPolicy;
use Chamilo\PluginBundle\ExeLearning\Service\PackageException;

$course_plugin = 'exelearning';

require_once __DIR__.'/config.php';

api_protect_course_script(true);

if (!$plugin->isEnabled()) {
    api_not_allowed(true);
}

$courseId = api_get_course_int_id();
$sessionId = api_get_session_id();
$canEdit = $plugin->canEdit();

if (!$canEdit && !$plugin->isToolVisible($courseId)) {
    api_not_allowed(true);
}

$listUrl = $plugin->url('start.php');

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    if (!$canEdit) {
        api_not_allowed(true);
    }

    $flash = static function (string $message, string $type) use ($listUrl): never {
        Display::addFlash(Display::return_message($message, $type));
        header('Location: '.$listUrl);

        exit;
    };

    if (!Security::check_token('post', null, ExeLearningPlugin::CSRF_PREFIX)) {
        $flash($plugin->get_lang('error_csrf'), 'error');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ('upload' === $action) {
        $file = $_FILES['package'] ?? null;
        if (!is_array($file) || \UPLOAD_ERR_OK !== ($file['error'] ?? \UPLOAD_ERR_NO_FILE) || !is_uploaded_file((string) $file['tmp_name'])) {
            $flash($plugin->get_lang(\UPLOAD_ERR_INI_SIZE === ($file['error'] ?? null) ? 'error_too_large' : 'error_upload'), 'error');
        }

        try {
            $plugin->createPackage((string) $file['tmp_name'], basename((string) $file['name']), (string) ($_POST['title'] ?? ''), $courseId, $sessionId);
        } catch (PackageException $e) {
            $flash($plugin->get_lang($e->getMessage()), 'error');
        }

        $flash($plugin->get_lang('package_uploaded'), 'success');
    }

    $package = $plugin->findPackage((int) ($_POST['id'] ?? 0));
    if (!$package instanceof ExeLearningPackage
        || !AccessPolicy::ownsPackage($package->getCourseId(), $package->getSessionId(), $courseId, $sessionId)) {
        api_not_allowed(true);
    }

    switch ($action) {
        case 'rename':
            $title = trim((string) ($_POST['title'] ?? ''));
            if ('' === $title) {
                $flash($plugin->get_lang('title_required'), 'error');
            }
            $package->setTitle($title);
            Database::getManager()->flush();
            $flash($plugin->get_lang('package_renamed'), 'success');

            // no break: $flash() exits
        case 'show':
        case 'hide':
            $package->setVisible('show' === $action);
            Database::getManager()->flush();
            $flash($plugin->get_lang('visibility_changed'), 'success');

            // no break: $flash() exits
        case 'delete':
            $plugin->deletePackage($package);
            $flash($plugin->get_lang('package_deleted'), 'success');

            // no break: $flash() exits
        default:
            api_not_allowed(true);
    }
}

$token = Security::get_existing_token(ExeLearningPlugin::CSRF_PREFIX);
$tokenField = '<input type="hidden" name="'.ExeLearningPlugin::CSRF_PREFIX.'_sec_token" value="'.htmlspecialchars($token, \ENT_QUOTES).'">';
$e = static fn ($value): string => htmlspecialchars((string) $value, \ENT_QUOTES, 'UTF-8');

$editorEnabled = $plugin->isEditorEnabled();
$packages = $plugin->packagesInContext($courseId, $sessionId);
if (!$canEdit) {
    $packages = array_values(array_filter($packages, static fn (ExeLearningPackage $p): bool => $p->isVisible()));
}

$html = '<link rel="stylesheet" href="'.$e($plugin->assetUrl('resources/css/exelearning.css')).'">';
$html .= '<div class="exelearning-tool">';

if ($canEdit) {
    $maxBytes = $plugin->maxUploadBytes();
    $html .= '<section class="exelearning-upload"><h3>'.$plugin->e('upload_package').'</h3>'
        .'<form method="post" enctype="multipart/form-data" action="'.$e($listUrl).'" data-testid="exelearning-upload-form">'
        .$tokenField.'<input type="hidden" name="action" value="upload">'
        .'<label>'.$plugin->e('package_file').' <input type="file" name="package" accept=".elpx,.zip" required></label> '
        .'<label>'.$plugin->e('title_optional').' <input type="text" name="title" maxlength="255"></label> '
        .'<button type="submit" class="btn btn--primary">'.$plugin->e('upload').'</button>'
        .'<p class="exelearning-help">'.$plugin->e('package_file_help')
        .($maxBytes > 0 ? ' ('.$e(ExeLearningPlugin::formatBytes($maxBytes)).')' : '').'</p>'
        .'</form>';
    if ($editorEnabled) {
        $html .= '<p><a class="btn btn--primary" href="'.$e($plugin->url('edit.php')).'">'.$plugin->e('new_package').'</a></p>';
    }
    $html .= '</section>';
}

if ([] === $packages) {
    $html .= '<p class="exelearning-empty">'.$plugin->e('no_packages').'</p>';
} else {
    $html .= '<table class="data_table exelearning-list"><thead><tr><th>'.$plugin->e('title').'</th><th>'
        .$plugin->e('language').'</th><th>'.$plugin->e('updated').'</th><th>'.$plugin->e('size').'</th>'
        .($canEdit ? '<th>'.$plugin->e('actions').'</th>' : '').'</tr></thead><tbody>';

    foreach ($packages as $package) {
        $id = (int) $package->getId();
        $owned = AccessPolicy::ownsPackage($package->getCourseId(), $package->getSessionId(), $courseId, $sessionId);
        $html .= '<tr data-package-id="'.$id.'"'.($package->isVisible() ? '' : ' class="exelearning-hidden"').'>'
            .'<td><a href="'.$e($plugin->url('view.php', ['id' => $id])).'">'.$e($package->getTitle()).'</a>'
            .($package->isVisible() ? '' : ' <em>('.$plugin->e('hidden').')</em>').'</td>'
            .'<td>'.$e($package->getLanguage()).'</td>'
            .'<td>'.$e(api_convert_and_format_date($package->getUpdatedAt()->format('Y-m-d H:i:s'), DATE_TIME_FORMAT_LONG)).'</td>'
            .'<td>'.$e(ExeLearningPlugin::formatBytes($package->getFileSize())).'</td>';

        if ($canEdit) {
            $html .= '<td class="exelearning-actions">'
                .'<a class="btn btn--plain" href="'.$e($plugin->url('download.php', ['id' => $id])).'">'.$plugin->e('download').'</a>';
            if ($owned) {
                if ($editorEnabled) {
                    $html .= ' <a class="btn btn--plain" href="'.$e($plugin->url('edit.php', ['id' => $id])).'">'.$plugin->e('edit').'</a>';
                }
                $form = static fn (string $action, string $label, string $extra = '', string $confirm = ''): string => '<form method="post" action="'.$e($listUrl).'" class="exelearning-inline"'
                    .('' !== $confirm ? ' onsubmit="return confirm('.$e(json_encode($confirm)).')"' : '').'>'
                    .$tokenField.'<input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.$id.'">'
                    .$extra.'<button type="submit" class="btn btn--plain">'.$e($label).'</button></form>';
                $html .= ' '.$form($package->isVisible() ? 'hide' : 'show', (string) $plugin->get_lang($package->isVisible() ? 'hide' : 'show'))
                    .' '.$form('rename', (string) $plugin->get_lang('rename'), '<input type="text" name="title" required maxlength="255" value="'.$e($package->getTitle()).'" aria-label="'.$plugin->e('title').'">')
                    .' '.$form('delete', (string) $plugin->get_lang('delete'), '', (string) $plugin->get_lang('confirm_delete'));
            }
            $html .= '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
}

$html .= '</div>';

$view = new Template($plugin->get_lang('plugin_title'));
$view->assign('header', $plugin->get_lang('plugin_title'));
$view->assign('content', $html);
$view->display_one_col_template();
