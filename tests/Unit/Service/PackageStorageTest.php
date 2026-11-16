<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Tests\Unit\Service;

use Chamilo\PluginBundle\ExeLearning\Service\PackageException;
use Chamilo\PluginBundle\ExeLearning\Service\PackageStorage;
use Chamilo\PluginBundle\ExeLearning\Service\PackageValidator;
use Chamilo\PluginBundle\ExeLearning\Service\ValidatedPackage;
use Chamilo\PluginBundle\ExeLearning\Tests\Support\ZipFactory;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\TestCase;

/**
 * Storage must never leave a half-written version behind, and a version is
 * only "there" once complete: that is what makes save atomic.
 */
final class PackageStorageTest extends TestCase
{
    private const string PREFIX = 'ExeLearning/c3/p7';

    protected function tearDown(): void
    {
        ZipFactory::cleanup();
    }

    public function testStoresTheOriginalAndEveryEntryUnderTheHashDirectory(): void
    {
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $zip = ZipFactory::valid(['content/css/a.css' => 'body{}']);
        $package = (new PackageValidator())->validate($zip, 'p.elpx');

        (new PackageStorage($fs))->store($zip, $package, self::PREFIX);

        $dir = self::PREFIX.'/'.$package->sha256;
        self::assertSame('body{}', $fs->read($dir.'/content/css/a.css'));
        self::assertTrue($fs->fileExists($dir.'/index.html'));
        self::assertSame((string) file_get_contents($zip), $fs->read($dir.'.elpx'));
        self::assertTrue((new PackageStorage($fs))->hasVersion(self::PREFIX, $package->sha256));
    }

    public function testAFailedWriteLeavesNothingBehind(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter {
            public int $writes = 0;

            public function writeStream(string $path, $contents, Config $config): void
            {
                if (++$this->writes > 1) {
                    throw UnableToWriteFile::atLocation($path, 'disk full');
                }
                parent::writeStream($path, $contents, $config);
            }
        };
        $fs = new Filesystem($adapter);
        $zip = ZipFactory::valid(['content/a.txt' => 'a']);
        $package = (new PackageValidator())->validate($zip, 'p.elpx');

        try {
            (new PackageStorage($fs))->store($zip, $package, self::PREFIX);
            self::fail('A storage failure must surface.');
        } catch (PackageException $e) {
            self::assertSame(PackageException::STORAGE, $e->getMessage());
        }

        self::assertSame([], $fs->listContents(self::PREFIX, true)->toArray());
        self::assertFalse((new PackageStorage($fs))->hasVersion(self::PREFIX, $package->sha256));
    }

    public function testRefusesEntriesLargerThanDeclared(): void
    {
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $zip = ZipFactory::valid(['content/a.txt' => str_repeat('a', 100)]);
        $real = (new PackageValidator())->validate($zip, 'p.elpx');
        // Same archive, but its central directory "declares" a smaller entry.
        $lying = new ValidatedPackage($real->metadata, ['content/a.txt' => 10] + $real->files, $real->sha256);

        $this->expectExceptionObject(new PackageException(PackageException::TOO_LARGE));
        (new PackageStorage($fs))->store($zip, $lying, self::PREFIX);
    }

    public function testStoringTheSameBytesTwiceIsANoOp(): void
    {
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $zip = ZipFactory::valid();
        $package = (new PackageValidator())->validate($zip, 'p.elpx');
        $storage = new PackageStorage($fs);

        $storage->store($zip, $package, self::PREFIX);
        $storage->store($zip, $package, self::PREFIX);

        self::assertTrue($storage->hasVersion(self::PREFIX, $package->sha256));
    }

    public function testDeleteVersionAndTreeRemoveFiles(): void
    {
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $storage = new PackageStorage($fs);
        $zip = ZipFactory::valid();
        $package = (new PackageValidator())->validate($zip, 'p.elpx');
        $storage->store($zip, $package, self::PREFIX);

        $storage->deleteVersion(self::PREFIX, $package->sha256);
        self::assertFalse($storage->hasVersion(self::PREFIX, $package->sha256));
        self::assertNull($storage->readStream(self::PREFIX.'/'.$package->sha256.'/index.html'));

        $storage->store($zip, $package, self::PREFIX);
        $storage->deleteTree(PackageStorage::coursePrefix(3));
        self::assertSame([], $fs->listContents(PackageStorage::ROOT, true)->toArray());
    }

    public function testReadsStreamsAndSizes(): void
    {
        $fs = new Filesystem(new InMemoryFilesystemAdapter());
        $fs->write('k', 'hello');
        $storage = new PackageStorage($fs);

        $stream = $storage->readStream('k');
        self::assertIsResource($stream);
        self::assertSame('hello', stream_get_contents($stream));
        self::assertSame(5, $storage->fileSize('k'));
        self::assertNull($storage->fileSize('missing'));
        self::assertSame('ExeLearning/c3/p7', PackageStorage::packagePrefix(3, 7));
    }
}
