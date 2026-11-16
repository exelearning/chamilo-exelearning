<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/**
 * Unattended fresh install of Chamilo 3 for local development and CI.
 *
 * Chamilo 3 ships no CLI installer. This script replays, step by step and with
 * Chamilo's own functions, the fresh-install branch of
 * public/main/install/index.php (lines 840-980 at v3.0.2): write .env, create
 * the schema, baseline the migrations, load the fixtures and run
 * finishInstallationWithContainer(). It is idempotent: it exits early when the
 * .env already says APP_INSTALLED=1.
 *
 * It is a development tool, never shipped in the plugin package.
 */

use Chamilo\CoreBundle\Framework\Container;
use Chamilo\CoreBundle\Helpers\ScimHelper;
use Chamilo\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Session\Session as HttpSession;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$root = getenv('CHAMILO_ROOT') ?: '/var/www/chamilo';
$envFile = $root.'/.env';

if (is_file($envFile) && preg_match("/^APP_INSTALLED='?1'?\$/m", (string) file_get_contents($envFile))) {
    fwrite(STDOUT, "Chamilo is already installed, nothing to do.\n");

    exit(0);
}

$env = static fn (string $name, string $default = ''): string => false !== getenv($name) ? (string) getenv($name) : $default;

ini_set('memory_limit', '-1');
set_time_limit(0);

require_once $root.'/vendor/autoload.php';

define('SYSTEM_INSTALLATION', 1);
define('INSTALL_TYPE_UPDATE', 'update');
define('FORM_FIELD_DISPLAY_LENGTH', 40);
define('DATABASE_FORM_FIELD_DISPLAY_LENGTH', 25);
define('MAX_FORM_FIELD_LENGTH', 80);

Container::$session = new HttpSession(new MockArraySessionStorage());

require_once $root.'/public/main/install/install.lib.php';

$webUrl = rtrim($env('CHAMILO_URL', 'http://localhost:8080'), '/').'/';
$language = $env('CHAMILO_LANGUAGE', 'en_US');

$params = [
    '{{DATABASE_HOST}}' => $env('DB_HOST', 'db'),
    '{{DATABASE_PORT}}' => $env('DB_PORT', '3306'),
    '{{DATABASE_NAME}}' => $env('DB_NAME', 'chamilo'),
    '{{DATABASE_USER}}' => $env('DB_USER', 'chamilo'),
    '{{DATABASE_PASSWORD}}' => $env('DB_PASSWORD', 'chamilo'),
    '{{APP_INSTALLED}}' => 1,
    '{{APP_ENCRYPT_METHOD}}' => 'bcrypt',
    '{{APP_SECRET}}' => generateRandomToken(),
    '{{DB_MANAGER_ENABLED}}' => '0',
    '{{SOFTWARE_NAME}}' => 'Chamilo',
    '{{SOFTWARE_URL}}' => 'https://chamilo.org',
    '{{DENY_DELETE_USERS}}' => '0',
    '{{HOSTING_TOTAL_SIZE_LIMIT}}' => '0',
    '{{THEME_FALLBACK}}' => 'chamilo',
    '{{PACKAGER}}' => 'chamilo',
    '{{DEFAULT_TEMPLATE}}' => 'default',
    '{{ADMIN_CHAMILO_ANNOUNCEMENTS_DISABLE}}' => '0',
    '{{SCIM_TOKEN}}' => ScimHelper::createToken(),
];

fwrite(STDOUT, "Writing $envFile\n");
updateEnvFile($root.'/.env.dist', $envFile, $params);

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s', $params['{{DATABASE_HOST}}'], $params['{{DATABASE_PORT}}']),
    $params['{{DATABASE_USER}}'],
    $params['{{DATABASE_PASSWORD}}']
);
setEnvDatabaseServerVersion($envFile, (string) $pdo->query('SELECT VERSION()')->fetchColumn());

(new Dotenv())->load($envFile);
$appEnv = (string) ($_SERVER['APP_ENV'] ?? 'dev');

$kernel = new Kernel($appEnv, true);
$application = new Application($kernel);
$application->setAutoExit(false);

$run = static function (string $command, array $args = []) use ($application): void {
    fwrite(STDOUT, "Running $command\n");
    $input = new ArrayInput(array_merge(['command' => $command], $args));
    $input->setInteractive(false);
    $code = $application->run($input, new ConsoleOutput());
    if (0 !== $code) {
        fwrite(STDERR, "$command failed with exit code $code\n");

        exit(1);
    }
};

$run('doctrine:schema:create');
$run('doctrine:migrations:sync-metadata-storage');
$run('doctrine:migrations:version', ['--add' => true, '--all' => true]);

$kernel->boot();
$kernel->getContainer()->get('translator')->setLocale($language);
$run('doctrine:fixtures:load');

$kernel->boot();
finishInstallationWithContainer(
    $kernel->getContainer(),
    $root.'/',
    'bcrypt',
    $env('CHAMILO_ADMIN_PASSWORD', 'admin'),
    'Admin',
    'Chamilo',
    'admin',
    $env('CHAMILO_ADMIN_EMAIL', 'admin@example.com'),
    '',
    $language,
    'eXeLearning',
    'https://exelearning.net',
    'Chamilo eXeLearning dev',
    'approval',
    1,
    '',
    'null://null',
    'noreply@example.com',
    'Chamilo',
    $kernel,
    $webUrl
);

// Same as chamilo-lms .github/workflows/playwright.yml after its install step:
// run in prod mode (the dev container rebuild exhausts memory and floods logs).
$envContents = (string) file_get_contents($envFile);
$envContents = str_replace(["APP_ENV='dev'", "APP_DEBUG='1'"], ["APP_ENV='prod'", "APP_DEBUG='0'"], $envContents);
file_put_contents($envFile, $envContents);

fwrite(STDOUT, "Chamilo installed at $webUrl\n");
