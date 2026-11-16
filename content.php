<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Content endpoint: content.php/<packageId>/<sha256>/<token>/<relative path>.
 *
 * Path-based so relative URLs inside the package (HTML, CSS url()) resolve to
 * this endpoint too. Serves only from the private plugins storage, only to the
 * logged-in user the token was minted for, only paths that pass PackagePath,
 * with explicit Content-Type, nosniff and a CSP per file kind.
 */

use Chamilo\PluginBundle\ExeLearning\Service\ContentPolicy;
use Chamilo\PluginBundle\ExeLearning\Service\PackagePath;
use Chamilo\PluginBundle\ExeLearning\Service\PackageStorage;

// Chamilo boots Symfony with this request: hide the package path from the
// router, which would 404 on it, and keep it for ourselves.
$exelearningPathInfo = (string) ($_SERVER['PATH_INFO'] ?? '');
$_SERVER['REQUEST_URI'] = (string) ($_SERVER['SCRIPT_NAME'] ?? '/plugin/ExeLearning/content.php');
unset($_SERVER['PATH_INFO'], $_SERVER['ORIG_PATH_INFO']);

require_once __DIR__.'/config.php';

$fail = static function (int $status): never {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo 404 === $status ? 'Not found' : 'Forbidden';

    exit;
};

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    $fail(403);
}

if (1 !== preg_match('#^/(\d+)/([a-f0-9]{64})/(\d+\.[a-f0-9]{64})(?:/(.*))?$#', $exelearningPathInfo, $m)) {
    $fail(404);
}

$userId = (int) api_get_user_id();
if ($userId <= 0 || api_is_anonymous() || !$plugin->isEnabled()) {
    $fail(403);
}

[, $packageId, $hash, $token] = $m;
$relative = '' === ($m[4] ?? '') ? 'index.html' : $m[4];

if (!$plugin->contentToken()->verify($token, $userId, (int) $packageId, $hash, time())) {
    $fail(403);
}

$package = $plugin->findPackage((int) $packageId);
if (null === $package || $package->getContentHash() !== $hash || !PackagePath::isSafe($relative) || str_ends_with($relative, '/')) {
    $fail(404);
}

$storage = $plugin->storage();
$key = PackageStorage::packagePrefix($package->getCourseId(), (int) $package->getId()).'/'.$hash.'/'.$relative;
$stream = $storage->readStream($key);
if (null === $stream) {
    $fail(404);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header_remove('Pragma');
header_remove('Expires');
foreach (ContentPolicy::headersFor($relative, 'document' === ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')) as $name => $value) {
    header($name.': '.$value);
}
$size = $storage->fileSize($key);
if (null !== $size) {
    header('Content-Length: '.$size);
}

if ('HEAD' !== $_SERVER['REQUEST_METHOD']) {
    fpassthru($stream);
}
fclose($stream);
