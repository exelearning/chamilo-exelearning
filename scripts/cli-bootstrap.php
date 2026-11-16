<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Boots Chamilo from the CLI acting as the platform admin (user 1), for the
 * dev seed and the integration tests. Run inside the Chamilo container with
 * --url=<public URL>. Never shipped in the plugin package.
 */

use Chamilo\CoreBundle\Framework\Container;
use ChamiloSession as Session;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

require_once '/var/www/html/public/main/inc/global.inc.php';

// Legacy APIs read the user from the session, Doctrine listeners read the
// security token and the access URL from the session.
Session::write('_user', ['id' => 1, 'user_id' => 1, 'status' => COURSEMANAGER]);
Session::write('access_url_id', 1);
$exelearningAdmin = api_get_user_entity(1);
Container::getTokenStorage()->setToken(new UsernamePasswordToken($exelearningAdmin, 'main', $exelearningAdmin->getRoles()));
