<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Tests\Unit\Service;

use Chamilo\PluginBundle\ExeLearning\Service\ContentPolicy;
use Chamilo\PluginBundle\ExeLearning\Service\PackagePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Package files run on the site origin: every response must have an explicit
 * type and nosniff, and SVG/XML must never get the scriptable HTML policy.
 */
final class ContentPolicyTest extends TestCase
{
    public function testHtmlGetsTheHtmlPolicyAndFrameAncestorsSelf(): void
    {
        $headers = ContentPolicy::headersFor('index.html', false);

        self::assertSame('text/html; charset=utf-8', $headers['Content-Type']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
        self::assertStringContainsString("frame-ancestors 'self'", $headers['Content-Security-Policy']);
        self::assertStringNotContainsString('sandbox', $headers['Content-Security-Policy']);
    }

    public function testTopLevelHtmlIsSandboxedIntoAnOpaqueOrigin(): void
    {
        $csp = ContentPolicy::headersFor('html/page.html', true)['Content-Security-Policy'];

        self::assertStringContainsString('sandbox allow-scripts', $csp);
        self::assertStringNotContainsString('allow-same-origin', $csp);
    }

    #[DataProvider('scriptFree')]
    public function testSvgAndXmlAreScriptFree(string $path): void
    {
        $headers = ContentPolicy::headersFor($path, false);

        self::assertSame(ContentPolicy::CSP_SCRIPT_FREE, $headers['Content-Security-Policy']);
        self::assertStringContainsString("default-src 'none'", $headers['Content-Security-Policy']);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function scriptFree(): iterable
    {
        yield ['img/logo.svg'];

        yield ['content.xml'];

        yield ['IMG/LOGO.SVG'];
    }

    public function testUnknownTypesAreDownloadedNeverSniffed(): void
    {
        $headers = ContentPolicy::headersFor('weird.xyz', false);

        self::assertSame('application/octet-stream', $headers['Content-Type']);
        self::assertSame('attachment', $headers['Content-Disposition']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function testAssetsGetTheirTypeAndNoCsp(): void
    {
        $headers = ContentPolicy::headersFor('content/css/base.css', false);

        self::assertSame('text/css; charset=utf-8', $headers['Content-Type']);
        self::assertArrayNotHasKey('Content-Security-Policy', $headers);
    }

    #[DataProvider('paths')]
    public function testPackagePathRule(string $path, bool $safe): void
    {
        self::assertSame($safe, PackagePath::isSafe($path));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'plain' => ['index.html', true];

        yield 'nested' => ['content/css/base.css', true];

        yield 'directory entry' => ['content/', true];

        yield 'unicode' => ['contenido/imágenes/ñ.png', true];

        yield 'empty' => ['', false];

        yield 'dot' => ['./index.html', false];

        yield 'dotdot' => ['a/../b', false];

        yield 'leading slash' => ['/index.html', false];

        yield 'double slash' => ['a//b', false];

        yield 'nul' => ["a\0b", false];

        yield 'newline' => ["a\nb", false];

        yield 'backslash' => ['a\b', false];

        yield 'drive' => ['c:/x', false];

        yield 'scheme' => ['javascript:alert(1)', false];

        yield 'too long' => [str_repeat('a', 1025), false];
    }

    public function testForbiddenNames(): void
    {
        self::assertTrue(PackagePath::isForbiddenName('a/b/x.PHP'));
        self::assertTrue(PackagePath::isForbiddenName('.user.ini'));
        self::assertTrue(PackagePath::isForbiddenName('x.phtml'));
        self::assertFalse(PackagePath::isForbiddenName('photo.png'));
        self::assertFalse(PackagePath::isForbiddenName('php-notes.html'));
    }
}
