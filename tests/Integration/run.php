<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Integration checks against a real Chamilo (the dev container or CI):
 * install idempotency, course and session deletion cleanup, user hard delete,
 * uninstall. Plain assertions, no framework: run with
 *   make test-integration
 * Pass --uninstall to also check uninstall/reinstall (wipes plugin data).
 */

use Chamilo\CoreBundle\Entity\Course;
use Chamilo\CoreBundle\Framework\Container;
use Chamilo\PluginBundle\ExeLearning\Entity\ExeLearningPackage;
use Chamilo\PluginBundle\ExeLearning\Service\PackageStorage;

require_once __DIR__.'/../../scripts/cli-bootstrap.php';

$failures = 0;
$check = static function (bool $ok, string $what) use (&$failures): void {
    fwrite(\STDOUT, ($ok ? 'ok   ' : 'FAIL ').$what."\n");
    $failures += $ok ? 0 : 1;
};

$plugin = ExeLearningPlugin::create();
$em = Database::getManager();
$schema = $em->getConnection()->createSchemaManager();
$fs = Container::getPluginsFileSystem();
$sample = static function (): string {
    $tmp = sys_get_temp_dir().'/exe-it-'.bin2hex(random_bytes(4)).'.elpx';
    copy(__DIR__.'/../fixtures/sample.elpx', $tmp);

    return $tmp;
};
$rows = static fn (array $criteria): int => count($em->getRepository(ExeLearningPackage::class)->findBy($criteria));

// 1. install() is idempotent.
$plugin->install();
$plugin->install();
$check($schema->tablesExist([ExeLearningPlugin::TABLE_PACKAGE]), 'install() twice keeps the table');
$check(AppPlugin::getInstance()->isInstalled('ExeLearning'), 'plugin is registered as installed');

// Created before any EntityManager::clear(): user creation needs the managed admin.
$login = 'exeit'.strtolower(bin2hex(random_bytes(2)));
$userId = (int) UserManager::create_user('IT', 'User', COURSEMANAGER, $login.'@example.com', $login, $login, '', 'en_US', '', '', [], null, 1, 0, [], '', false, false, '', false, null, 1);

// 2. Course deletion removes rows and files.
$code = 'EXEIT'.strtoupper(bin2hex(random_bytes(2)));
$courseId = (int) CourseManager::create_course(['title' => 'IT '.$code, 'wanted_code' => $code], 1)->getId();
$package = $plugin->createPackage($sample(), 'sample.elpx', 'IT package', $courseId, 0);
$prefix = PackageStorage::packagePrefix($courseId, (int) $package->getId());
$check($fs->fileExists($prefix.'/'.$package->getContentHash().'/index.html'), 'package extracted to the plugins storage');
$em->clear();
CourseManager::delete_course($code);
$em->clear();
$check(0 === $rows(['course' => $courseId]), 'course deletion removes package rows');
$check(!$fs->directoryExists(PackageStorage::coursePrefix($courseId)), 'course deletion removes stored files');

// 3. Session deletion removes the session's packages only.
$base = $em->getRepository(Course::class)->findOneBy(['code' => 'EXEDEMO']);
$baseId = (int) $base->getId();
$sessionId = (int) SessionManager::create_session('IT session '.$code, api_get_utc_datetime(), api_get_utc_datetime(time() + 86400), null, null, null, null, [1], 0, 1, false, null, '');
SessionManager::add_courses_to_session($sessionId, [$baseId]);
$sessionPackage = $plugin->createPackage($sample(), 'sample.elpx', 'IT session package', $baseId, $sessionId);
$sessionPrefix = PackageStorage::packagePrefix($baseId, (int) $sessionPackage->getId());
$baseRowsBefore = $rows(['course' => $baseId, 'session' => null]);
$em->clear();
SessionManager::delete($sessionId);
$em->clear();
$check(0 === $rows(['session' => $sessionId]), 'session deletion removes the session packages');
$check(!$fs->directoryExists($sessionPrefix), 'session deletion removes their files');
$check($baseRowsBefore === $rows(['course' => $baseId, 'session' => null]), 'session deletion keeps base-course packages');

// 4. Hard-deleting the creator keeps the package (ON DELETE SET NULL).
$owned = $plugin->createPackage($sample(), 'sample.elpx', 'IT owned', $baseId, 0);
$em->getConnection()->executeStatement('UPDATE plugin_exelearning_package SET user_id = :u WHERE id = :id', ['u' => $userId, 'id' => $owned->getId()]);
$em->getConnection()->executeStatement('DELETE FROM user WHERE id = :u', ['u' => $userId]);
$em->clear();
$survivor = $em->find(ExeLearningPackage::class, $owned->getId());
$check(null !== $survivor && null === $survivor->getUser(), 'hard-deleting the creator keeps the package with no owner');
$plugin->deletePackage($survivor);

// 5. Uninstall drops the table and the stored files; install recreates it.
if (in_array('--uninstall', $argv, true)) {
    $plugin->uninstall();
    $check(!$schema->tablesExist([ExeLearningPlugin::TABLE_PACKAGE]), 'uninstall() drops the table');
    $check(!$fs->directoryExists(PackageStorage::ROOT), 'uninstall() removes every stored package');
    $plugin->install();
    $check($schema->tablesExist([ExeLearningPlugin::TABLE_PACKAGE]), 'install() after uninstall recreates the table');
}

fwrite(\STDOUT, 0 === $failures ? "All integration checks passed.\n" : "$failures integration check(s) failed.\n");

exit(0 === $failures ? 0 : 1);
