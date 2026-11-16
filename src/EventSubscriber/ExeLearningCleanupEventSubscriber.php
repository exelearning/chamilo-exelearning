<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

use Chamilo\CoreBundle\Event\AbstractEvent;
use Chamilo\CoreBundle\Event\CourseDeletedEvent;
use Chamilo\CoreBundle\Event\Events;
use Chamilo\CoreBundle\Event\SessionDeletedEvent;
use Chamilo\PluginBundle\ExeLearning\Entity\ExeLearningPackage;
use Chamilo\PluginBundle\ExeLearning\Service\PackageStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Removes packages (rows and stored files) when their course or session is deleted.
 *
 * Global namespace and *EventSubscriber.php file name: the only form
 * PluginEventSubscriberPass registers. Acts on TYPE_PRE and is guarded on
 * "installed", not "enabled": rows outlive a disabled plugin.
 *
 * Users need no subscriber: plugin_exelearning_package.user_id is
 * ON DELETE SET NULL, and a soft-deleted user keeps its rows anyway.
 */
class ExeLearningCleanupEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            Events::COURSE_DELETED => 'onCourseDeleted',
            Events::SESSION_DELETED => 'onSessionDeleted',
        ];
    }

    public function onCourseDeleted(CourseDeletedEvent $event): void
    {
        $courseId = (int) $event->getCourseId();
        if (AbstractEvent::TYPE_PRE !== $event->getType() || $courseId <= 0 || !$this->isInstalled()) {
            return;
        }

        ExeLearningPlugin::create()->storage()->deleteTree(PackageStorage::coursePrefix($courseId));

        Database::getManager()
            ->createQuery('DELETE FROM '.ExeLearningPackage::class.' p WHERE IDENTITY(p.course) = :course')
            ->setParameter('course', $courseId)
            ->execute()
        ;
    }

    public function onSessionDeleted(SessionDeletedEvent $event): void
    {
        $sessionId = (int) $event->getSessionId();
        if (AbstractEvent::TYPE_PRE !== $event->getType() || $sessionId <= 0 || !$this->isInstalled()) {
            return;
        }

        $plugin = ExeLearningPlugin::create();
        $em = Database::getManager();
        $packages = $em->getRepository(ExeLearningPackage::class)->findBy(['session' => $sessionId]);
        foreach ($packages as $package) {
            $plugin->deletePackage($package);
        }
    }

    private function isInstalled(): bool
    {
        return AppPlugin::getInstance()->isInstalled(ExeLearningPlugin::create()->get_name());
    }
}
