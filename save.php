<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Save endpoint for the embedded editor. POST only, multipart:
 *   package (the .elpx bytes), id (0 = new), expected_hash, title, CSRF token.
 * Replies JSON. A corrupt or rejected package never replaces the current one;
 * a save based on an outdated version is refused with 409.
 */

use Chamilo\PluginBundle\ExeLearning\Service\PackageException;

$course_plugin = 'exelearning';

require_once __DIR__.'/config.php';

$reply = static function (int $status, array $body): never {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($body, \JSON_UNESCAPED_SLASHES);

    exit;
};

if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
    $reply(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

api_protect_course_script(false);

if (!$plugin->isEnabled() || !$plugin->canEdit() || !$plugin->isEditorEnabled()) {
    $reply(403, ['ok' => false, 'error' => $plugin->get_lang('not_allowed')]);
}

if (!Security::check_token('post', null, ExeLearningPlugin::CSRF_PREFIX)) {
    $reply(403, ['ok' => false, 'error' => $plugin->get_lang('error_csrf')]);
}

$file = $_FILES['package'] ?? null;
if (!is_array($file) || \UPLOAD_ERR_OK !== ($file['error'] ?? \UPLOAD_ERR_NO_FILE) || !is_uploaded_file((string) $file['tmp_name'])) {
    $reply(413, ['ok' => false, 'error' => $plugin->get_lang('error_too_large')]);
}

$tmp = (string) $file['tmp_name'];
$id = (int) ($_POST['id'] ?? 0);

try {
    if (0 === $id) {
        $package = $plugin->createPackage($tmp, 'package.elpx', (string) ($_POST['title'] ?? ''), api_get_course_int_id(), api_get_session_id());
    } else {
        $package = $plugin->packageForEdit($id);
        if (null === $package) {
            $reply(403, ['ok' => false, 'error' => $plugin->get_lang('not_allowed')]);
        }
        if (!$plugin->replacePackage($package, $tmp, 'package.elpx', (string) ($_POST['expected_hash'] ?? ''))) {
            $reply(409, ['ok' => false, 'error' => $plugin->get_lang('save_conflict')]);
        }
        Database::getManager()->refresh($package);
    }
} catch (PackageException $e) {
    // The reason is a translation key: safe to log, useful to diagnose editor exports.
    error_log('[ExeLearning] save rejected: '.$e->getMessage());
    $reply(422, ['ok' => false, 'error' => $plugin->get_lang($e->getMessage())]);
}

$reply(200, ['ok' => true, 'id' => (int) $package->getId(), 'hash' => $package->getContentHash()]);
