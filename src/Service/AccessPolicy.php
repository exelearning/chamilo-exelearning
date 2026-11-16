<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Service;

/**
 * Permission decisions, as pure functions of facts gathered from Chamilo.
 *
 * Kept free of Chamilo calls so every rule is unit-tested; the plugin class
 * collects the facts (api_is_platform_admin(), api_is_allowed_to_edit(), ...).
 */
final class AccessPolicy
{
    public const string ROLES_TEACHERS = 'teachers';
    public const string ROLES_TEACHERS_COACHES = 'teachers_coaches';
    public const string ROLES_ADMINS = 'admins';

    /**
     * Whether the user may create, edit and manage packages in the current context.
     *
     * @param bool   $isPlatformAdmin api_is_platform_admin()
     * @param bool   $isTeacher       api_is_allowed_to_edit() (course teacher or admin)
     * @param bool   $isCoach         api_is_allowed_to_edit(false, true) (also session coaches)
     * @param string $editRoles       the edit_roles setting
     */
    public static function canEdit(bool $isPlatformAdmin, bool $isTeacher, bool $isCoach, string $editRoles): bool
    {
        if ($isPlatformAdmin) {
            return true;
        }

        return match ($editRoles) {
            self::ROLES_ADMINS => false,
            self::ROLES_TEACHERS_COACHES => $isTeacher || $isCoach,
            default => $isTeacher,
        };
    }

    /**
     * Whether a package belongs to the current course/session context and may
     * be changed from it. In a session, only that session's packages; outside
     * a session, only base-course packages.
     */
    public static function ownsPackage(int $packageCourseId, int $packageSessionId, int $courseId, int $sessionId): bool
    {
        return $packageCourseId === $courseId && $packageSessionId === $sessionId;
    }

    /**
     * Whether a package is visible from the current context: base-course
     * packages are visible in every session of the course; session packages
     * only in their session.
     */
    public static function isInContext(int $packageCourseId, int $packageSessionId, int $courseId, int $sessionId): bool
    {
        return $packageCourseId === $courseId && (0 === $packageSessionId || $packageSessionId === $sessionId);
    }

    /**
     * Whether the user may open a package.
     */
    public static function canView(bool $isEditor, bool $packageVisible, bool $toolVisible, bool $inContext): bool
    {
        if (!$inContext) {
            return false;
        }

        return $isEditor || ($packageVisible && $toolVisible);
    }
}
