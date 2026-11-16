<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Tests\Unit\Service;

use Chamilo\PluginBundle\ExeLearning\Service\EditorBootstrap;
use Chamilo\PluginBundle\ExeLearning\Service\PackageMetadata;
use Chamilo\PluginBundle\ExeLearning\Tests\Support\ZipFactory;
use PHPUnit\Framework\TestCase;

/**
 * Metadata is read, never trusted as markup; the editor page gets exactly one
 * trusted parent origin.
 */
final class MetadataAndBootstrapTest extends TestCase
{
    public function testReadsOdeProperties(): void
    {
        $meta = PackageMetadata::fromContentXml(ZipFactory::CONTENT_XML);

        self::assertNotNull($meta);
        self::assertSame('Test package', $meta->title);
        self::assertSame('A description', $meta->description);
        self::assertSame('en', $meta->language);
        self::assertSame('CC BY-SA 4.0', $meta->license);
        self::assertSame('4.0', $meta->version);
    }

    public function testRejectsNonOdeAndBrokenXml(): void
    {
        self::assertNull(PackageMetadata::fromContentXml(''));
        self::assertNull(PackageMetadata::fromContentXml('<foo/>'));
        self::assertNull(PackageMetadata::fromContentXml('<ode><unclosed>'));
    }

    public function testDoesNotExpandExternalEntities(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE ode [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
            .'<ode><odeProperties><odeProperty><key>pp_title</key><value>&x;</value></odeProperty></odeProperties></ode>';

        $meta = PackageMetadata::fromContentXml($xml);

        self::assertNotNull($meta);
        self::assertStringNotContainsString('root:', $meta->title);
    }

    public function testMissingPropertiesAreEmpty(): void
    {
        $meta = PackageMetadata::fromContentXml('<ode xmlns="http://www.intef.es/xsd/ode"/>');

        self::assertNotNull($meta);
        self::assertSame('', $meta->title);
    }

    public function testEditorBootstrapInjectsBaseAndConfig(): void
    {
        $html = EditorBootstrap::render(
            '<html><head><script src="./app/app.js"></script></head><body></body></html>',
            'https://lms.example/plugin/ExeLearning/dist/static',
            'https://lms.example',
            'https://lms.example/plugin/ExeLearning/download.php?id=1',
            'es'
        );

        self::assertStringContainsString('<base href="https://lms.example/plugin/ExeLearning/dist/static/">', $html);
        self::assertStringContainsString('src="https://lms.example/plugin/ExeLearning/dist/static/app/app.js"', $html);
        self::assertStringContainsString('"trustedOrigins":["https://lms.example"]', $html);
        self::assertStringContainsString('"parentOrigin":"https://lms.example"', $html);
        self::assertStringContainsString('"initialProjectUrl":"https://lms.example/plugin/ExeLearning/download.php?id=1"', $html);
    }

    public function testEditorBootstrapEscapesConfigForScriptContext(): void
    {
        $html = EditorBootstrap::render('<head></head>', 'https://x/s', 'https://x', 'https://x/d?a=</script><script>alert(1)</script>', 'es');

        self::assertStringNotContainsString('</script><script>alert(1)', $html);
    }

    public function testAvailabilityNeedsIndexAndAppDirectory(): void
    {
        $dir = sys_get_temp_dir().'/exe-static-'.bin2hex(random_bytes(4));
        mkdir($dir);
        self::assertFalse(EditorBootstrap::isAvailable($dir));
        file_put_contents($dir.'/index.html', 'x');
        self::assertFalse(EditorBootstrap::isAvailable($dir));
        mkdir($dir.'/app');
        self::assertTrue(EditorBootstrap::isAvailable($dir));
        rmdir($dir.'/app');
        unlink($dir.'/index.html');
        rmdir($dir);
    }
}
