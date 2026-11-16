<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Called by AppPlugin::uninstall() from the plugin administration.
 */
if (!api_is_platform_admin()) {
    exit('You must have admin permissions to uninstall plugins');
}

ExeLearningPlugin::create()->uninstall();
