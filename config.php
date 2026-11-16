<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Bootstrap shared by the plugin's pages: boots Chamilo (legacy mode) and
 * exposes $plugin. Plugin classes are loaded through Chamilo's Composer
 * classmap on public/plugin (run `composer dump-autoload` after installing).
 */
require_once __DIR__.'/../../main/inc/global.inc.php';

$plugin = ExeLearningPlugin::create();
