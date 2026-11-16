<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

/*
 * Development seed, run inside the Chamilo container by `make seed`:
 * installs and enables the plugin for the default access URL (what
 * main/inc/ajax/plugin.ajax.php does), creates teacher/student accounts, a
 * demo course with both enrolled, adds the course tool and uploads a sample
 * package. Idempotent. Never shipped in the plugin package.
 */

use Chamilo\CoreBundle\Entity\Course;
use Chamilo\CoreBundle\Entity\Plugin as PluginEntity;
use Chamilo\CoreBundle\Framework\Container;
use Chamilo\PluginBundle\ExeLearning\Entity\ExeLearningPackage;

require_once __DIR__.'/cli-bootstrap.php';

$log = static fn (string $message) => fwrite(\STDOUT, $message."\n");

$em = Database::getManager();
$plugin = ExeLearningPlugin::create();

// 1. Install + enable for the current access URL, as plugin.ajax.php does.
$plugin->install();
$repo = $em->getRepository(PluginEntity::class);
$entity = $repo->findOneBy(['title' => $plugin->get_name()]) ?? new PluginEntity();
$entity->setTitle($plugin->get_name())->setInstalled(true)->setInstalledVersion(ExeLearningPlugin::VERSION);
$em->persist($entity);
$em->flush();
$entity->enable(Container::getAccessUrlUtil()->getCurrent());
$em->persist($entity);
$em->flush();
$log('Plugin installed and enabled.');

// 2. Users.
$users = [];
foreach ([['teacher', COURSEMANAGER, 'Tina', 'Teacher'], ['student', STUDENT, 'Sam', 'Student'], ['outsider', STUDENT, 'Olga', 'Outsider']] as [$login, $status, $first, $last]) {
    $id = (int) UserManager::get_user_id_from_username($login);
    if ($id <= 0) {
        $id = (int) UserManager::create_user($first, $last, $status, $login.'@example.com', $login, $login, '', 'en_US', '', '', [], null, 1, 0, [], '', false, false, '', false, null, 1);
        $log("User $login created.");
    }
    $users[$login] = $id;
}

// 3. Demo course with teacher and student.
$course = $em->getRepository(Course::class)->findOneBy(['code' => 'EXEDEMO']);
if (null === $course) {
    $course = CourseManager::create_course(['title' => 'eXeLearning demo', 'wanted_code' => 'EXEDEMO'], 1);
    $log('Course EXEDEMO created.');
}
// Enrolled users only, so the "not enrolled" paths can be tested.
$course->setVisibility(COURSE_VISIBILITY_REGISTERED);
$em->flush();
$courseId = (int) $course->getId();
CourseManager::subscribeUser($users['teacher'], $courseId, COURSEMANAGER, 0, 0, false);
CourseManager::subscribeUser($users['student'], $courseId, STUDENT, 0, 0, false);

// 4. Course tool in every course (as enabling from the admin page does).
$plugin->install_course_fields_in_all_courses(true);
$log('Course tool added.');

// 5. Sample package.
$existing = $em->getRepository(ExeLearningPackage::class)->findOneBy(['course' => $course]);
if (null === $existing) {
    $sample = sys_get_temp_dir().'/exelearning-sample.elpx';
    copy(__DIR__.'/../tests/fixtures/sample.elpx', $sample);
    $package = $plugin->createPackage($sample, 'sample.elpx', '', $courseId, 0);
    unlink($sample);
    $log('Sample package uploaded: '.$package->getTitle().' (#'.$package->getId().').');
}

$log("Seed done. Course id $courseId: http://localhost:8080/course/$courseId/home");
