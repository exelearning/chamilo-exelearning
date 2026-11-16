<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Plugin metadata, read by Chamilo's plugin administration.
 */
if (!class_exists('ExeLearningPlugin')) {
    // Before `composer dump-autoload` the classmap does not know the class yet.
    require_once __DIR__.'/src/ExeLearningPlugin.php';
}

$plugin_info = ExeLearningPlugin::create()->get_info();
