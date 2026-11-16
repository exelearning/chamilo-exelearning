<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Tests\Unit\Service;

use Chamilo\PluginBundle\ExeLearning\Service\AccessPolicy;
use Chamilo\PluginBundle\ExeLearning\Service\ContentToken;
use PHPUnit\Framework\TestCase;

/**
 * Who may edit, which package a context owns, who may view, and the content
 * capability that carries the view decision to asset requests.
 */
final class AccessAndTokenTest extends TestCase
{
    public function testPlatformAdminsCanAlwaysEdit(): void
    {
        self::assertTrue(AccessPolicy::canEdit(true, false, false, AccessPolicy::ROLES_ADMINS));
    }

    public function testEditRolesSetting(): void
    {
        // Teacher-only (default).
        self::assertTrue(AccessPolicy::canEdit(false, true, true, AccessPolicy::ROLES_TEACHERS));
        self::assertFalse(AccessPolicy::canEdit(false, false, true, AccessPolicy::ROLES_TEACHERS));
        self::assertTrue(AccessPolicy::canEdit(false, true, true, ''));
        // Coaches added.
        self::assertTrue(AccessPolicy::canEdit(false, false, true, AccessPolicy::ROLES_TEACHERS_COACHES));
        // Admins only.
        self::assertFalse(AccessPolicy::canEdit(false, true, true, AccessPolicy::ROLES_ADMINS));
        // Students never.
        self::assertFalse(AccessPolicy::canEdit(false, false, false, AccessPolicy::ROLES_TEACHERS_COACHES));
    }

    public function testOwnershipIsExactCourseAndSession(): void
    {
        self::assertTrue(AccessPolicy::ownsPackage(3, 0, 3, 0));
        self::assertTrue(AccessPolicy::ownsPackage(3, 5, 3, 5));
        self::assertFalse(AccessPolicy::ownsPackage(3, 0, 4, 0), 'another course');
        self::assertFalse(AccessPolicy::ownsPackage(3, 0, 3, 5), 'session cannot change base-course packages');
        self::assertFalse(AccessPolicy::ownsPackage(3, 5, 3, 0), 'base course cannot change session packages');
        self::assertFalse(AccessPolicy::ownsPackage(3, 5, 3, 6), 'another session');
    }

    public function testContextVisibility(): void
    {
        self::assertTrue(AccessPolicy::isInContext(3, 0, 3, 5), 'base-course packages show in sessions');
        self::assertFalse(AccessPolicy::isInContext(3, 5, 3, 6));
        self::assertFalse(AccessPolicy::isInContext(3, 5, 3, 0));
        self::assertFalse(AccessPolicy::isInContext(3, 0, 4, 0));
    }

    public function testViewRules(): void
    {
        self::assertTrue(AccessPolicy::canView(false, true, true, true));
        self::assertFalse(AccessPolicy::canView(false, false, true, true), 'hidden package');
        self::assertFalse(AccessPolicy::canView(false, true, false, true), 'hidden tool');
        self::assertTrue(AccessPolicy::canView(true, false, false, true), 'editors see hidden packages');
        self::assertFalse(AccessPolicy::canView(true, true, true, false), 'nobody sees out-of-context packages');
    }

    public function testTokensAreBoundToUserPackageHashAndTime(): void
    {
        $tokens = new ContentToken('secret');
        $hash = str_repeat('a', 64);
        $token = $tokens->issue(7, 1, $hash, 2000);

        self::assertTrue($tokens->verify($token, 7, 1, $hash, 1999));
        self::assertFalse($tokens->verify($token, 8, 1, $hash, 1999), 'other user');
        self::assertFalse($tokens->verify($token, 7, 2, $hash, 1999), 'other package');
        self::assertFalse($tokens->verify($token, 7, 1, str_repeat('b', 64), 1999), 'other version');
        self::assertFalse($tokens->verify($token, 7, 1, $hash, 2001), 'expired');
        self::assertFalse($tokens->verify($token, 0, 1, $hash, 1999), 'anonymous');
        self::assertFalse((new ContentToken('other'))->verify($token, 7, 1, $hash, 1999), 'other secret');
        self::assertFalse($tokens->verify('3000.'.str_repeat('0', 64), 7, 1, $hash, 1999), 'forged expiry');
        self::assertFalse($tokens->verify('garbage', 7, 1, $hash, 1999));
    }
}
