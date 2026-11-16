<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Tests\Unit\Service;

use Chamilo\PluginBundle\ExeLearning\Service\PackageException;
use Chamilo\PluginBundle\ExeLearning\Service\PackageValidator;
use Chamilo\PluginBundle\ExeLearning\Tests\Support\ZipFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The validator is the only gate between an uploaded file and storage: every
 * rejection here is a file that would otherwise be extracted.
 */
final class PackageValidatorTest extends TestCase
{
    protected function tearDown(): void
    {
        ZipFactory::cleanup();
    }

    public function testAcceptsTheRealSamplePackageAndReadsItsMetadata(): void
    {
        $result = (new PackageValidator())->validate(__DIR__.'/../../fixtures/sample.elpx', 'sample.elpx');

        self::assertSame('Ejemplo', $result->metadata->title);
        self::assertSame('es', $result->metadata->language);
        self::assertArrayHasKey('index.html', $result->files);
        self::assertSame(hash_file('sha256', __DIR__.'/../../fixtures/sample.elpx'), $result->sha256);
    }

    public function testAcceptsAZipExtensionToo(): void
    {
        $result = (new PackageValidator())->validate(ZipFactory::valid(), 'exported.zip');

        self::assertSame('Test package', $result->metadata->title);
    }

    /**
     * @param array<string, string> $entries
     */
    #[DataProvider('maliciousEntries')]
    public function testRejectsMaliciousEntryNames(array $entries, string $expected): void
    {
        $this->expectExceptionObject(new PackageException($expected));

        (new PackageValidator())->validate(ZipFactory::valid($entries), 'p.elpx');
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function maliciousEntries(): iterable
    {
        yield 'parent traversal' => [['../evil.html' => 'x'], PackageException::UNSAFE_PATH];

        yield 'nested traversal' => [['content/../../evil.html' => 'x'], PackageException::UNSAFE_PATH];

        yield 'absolute path' => [['/etc/cron.d/evil' => 'x'], PackageException::UNSAFE_PATH];

        yield 'windows drive' => [['C:/evil.html' => 'x'], PackageException::UNSAFE_PATH];

        yield 'backslash' => [['content\..\evil.html' => 'x'], PackageException::UNSAFE_PATH];

        yield 'php file' => [['content/shell.php' => '<?php'], PackageException::UNSAFE_PATH];

        yield 'htaccess' => [['.htaccess' => 'x'], PackageException::UNSAFE_PATH];

        yield 'stream wrapper' => [['phar://x' => 'x'], PackageException::UNSAFE_PATH];
    }

    public function testRejectsSymlinks(): void
    {
        $path = ZipFactory::make(['content.xml' => ZipFactory::CONTENT_XML, 'index.html' => 'x', 'link' => '/etc/passwd'], ['link']);

        $this->expectExceptionObject(new PackageException(PackageException::SYMLINK));
        (new PackageValidator())->validate($path, 'p.elpx');
    }

    public function testRejectsAZipBombByCompressionRatio(): void
    {
        $path = ZipFactory::valid(['content/zeros.bin' => str_repeat("\0", 8 * 1_048_576)]);

        $this->expectExceptionObject(new PackageException(PackageException::COMPRESSION_RATIO));
        (new PackageValidator())->validate($path, 'p.elpx');
    }

    public function testRejectsTooManyEntries(): void
    {
        $path = ZipFactory::valid(['a.txt' => 'a', 'b.txt' => 'b', 'c.txt' => 'c']);

        $this->expectExceptionObject(new PackageException(PackageException::TOO_MANY_ENTRIES));
        (new PackageValidator(maxEntries: 4))->validate($path, 'p.elpx');
    }

    public function testRejectsContentLargerThanTheUncompressedLimit(): void
    {
        $this->expectExceptionObject(new PackageException(PackageException::TOO_LARGE));
        (new PackageValidator(maxUncompressedBytes: 100))->validate(ZipFactory::valid(['big.txt' => str_repeat('x', 200)]), 'p.elpx');
    }

    public function testRejectsLegacyElpByExtension(): void
    {
        $this->expectExceptionObject(new PackageException(PackageException::LEGACY_ELP));
        (new PackageValidator())->validate(ZipFactory::valid(), 'old.elp');
    }

    public function testRejectsLegacyElpByContentv3(): void
    {
        $path = ZipFactory::make(['contentv3.xml' => '<x/>', 'index.html' => 'x']);

        $this->expectExceptionObject(new PackageException(PackageException::LEGACY_ELP));
        (new PackageValidator())->validate($path, 'renamed.elpx');
    }

    public function testRejectsArchivesWithoutContentXml(): void
    {
        $this->expectExceptionObject(new PackageException(PackageException::NOT_EXELEARNING));
        (new PackageValidator())->validate(ZipFactory::make(['index.html' => 'x']), 'p.elpx');
    }

    public function testRejectsContentXmlThatIsNotAnOdeDocument(): void
    {
        $this->expectExceptionObject(new PackageException(PackageException::NOT_EXELEARNING));
        (new PackageValidator())->validate(ZipFactory::make(['content.xml' => '<foo/>', 'index.html' => 'x']), 'p.elpx');
    }

    public function testRejectsPackagesWithoutIndexHtml(): void
    {
        $this->expectExceptionObject(new PackageException(PackageException::MISSING_INDEX));
        (new PackageValidator())->validate(ZipFactory::make(['content.xml' => ZipFactory::CONTENT_XML]), 'p.elpx');
    }

    public function testRejectsFilesThatAreNotZipArchives(): void
    {
        $this->expectExceptionObject(new PackageException(PackageException::INVALID_ZIP));
        (new PackageValidator())->validate(ZipFactory::raw('definitely not a zip'), 'p.elpx');
    }

    public function testRejectsTruncatedArchives(): void
    {
        $bytes = (string) file_get_contents(ZipFactory::valid());

        $this->expectExceptionObject(new PackageException(PackageException::INVALID_ZIP));
        (new PackageValidator())->validate(ZipFactory::raw(substr($bytes, 0, (int) (\strlen($bytes) * 0.6))), 'p.elpx');
    }

    public function testRejectsOtherExtensions(): void
    {
        $this->expectExceptionObject(new PackageException(PackageException::BAD_EXTENSION));
        (new PackageValidator())->validate(ZipFactory::valid(), 'p.docx');
    }
}
