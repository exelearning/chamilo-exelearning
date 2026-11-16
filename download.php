<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Download the original .elpx of the current version. Read-only (GET), for
 * users who may edit packages here. Also the initialProjectUrl the embedded
 * editor opens.
 */

use Chamilo\PluginBundle\ExeLearning\Service\PackageStorage;

$course_plugin = 'exelearning';

require_once __DIR__.'/config.php';

api_protect_course_script(true);

if (!$plugin->isEnabled()) {
    api_not_allowed(true);
}

$package = $plugin->canEdit() ? $plugin->packageForView((int) ($_GET['id'] ?? 0)) : null;
if (null === $package) {
    api_not_allowed(true);
}

$storage = $plugin->storage();
$key = PackageStorage::packagePrefix($package->getCourseId(), (int) $package->getId()).'/'.$package->getContentHash().'.elpx';
$stream = $storage->readStream($key);
if (null === $stream) {
    api_not_allowed(true);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$name = preg_replace('/[^\w.-]+/u', '_', $package->getTitle()) ?: 'package';
header('Content-Type: application/zip');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="'.$name.'.elpx"; filename*=UTF-8\'\''.rawurlencode($package->getTitle().'.elpx'));
header('Cache-Control: private, no-store');
$size = $storage->fileSize($key);
if (null !== $size) {
    header('Content-Length: '.$size);
}
fpassthru($stream);
fclose($stream);
