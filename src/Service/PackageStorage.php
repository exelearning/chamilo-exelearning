<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Service;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Throwable;
use ZipArchive;

/**
 * Stores packages in Chamilo's private "plugins" Flysystem storage.
 *
 * Layout, per package:
 *   ExeLearning/c<courseId>/p<packageId>/<sha256>.elpx   original upload
 *   ExeLearning/c<courseId>/p<packageId>/<sha256>/...    extracted content
 *
 * A version is written completely before the caller switches the package to
 * it, and a failed write removes everything it wrote: an interrupted or
 * corrupt save never touches the version currently served.
 */
final class PackageStorage
{
    public const string ROOT = 'ExeLearning';

    private const int CHUNK = 65_536;

    public function __construct(
        private readonly FilesystemOperator $filesystem,
    ) {}

    public static function packagePrefix(int $courseId, int $packageId): string
    {
        return \sprintf('%s/c%d/p%d', self::ROOT, $courseId, $packageId);
    }

    public static function coursePrefix(int $courseId): string
    {
        return \sprintf('%s/c%d', self::ROOT, $courseId);
    }

    /**
     * Write the original archive and its validated entries under $prefix/<sha256>.
     *
     * @throws PackageException when anything fails; partial writes are removed
     */
    public function store(string $zipPath, ValidatedPackage $package, string $prefix): void
    {
        $versionDir = $prefix.'/'.$package->sha256;

        try {
            if ($this->filesystem->fileExists($versionDir.'.elpx')) {
                return; // Same bytes already stored: content-addressed, nothing to do.
            }

            $zip = new ZipArchive();
            if (true !== $zip->open($zipPath, ZipArchive::RDONLY)) {
                throw new PackageException(PackageException::INVALID_ZIP);
            }

            try {
                foreach ($package->files as $name => $declaredSize) {
                    $this->copyEntry($zip, $name, $declaredSize, $versionDir.'/'.$name);
                }
            } finally {
                $zip->close();
            }

            $original = fopen($zipPath, 'rb');
            if (false === $original) {
                throw new PackageException(PackageException::STORAGE);
            }

            try {
                // Written last: its presence marks the version as complete.
                $this->filesystem->writeStream($versionDir.'.elpx', $original);
            } finally {
                if (\is_resource($original)) {
                    fclose($original);
                }
            }
        } catch (Throwable $e) {
            $this->deleteVersion($prefix, $package->sha256);

            throw $e instanceof PackageException ? $e : new PackageException(PackageException::STORAGE, 0, $e);
        }
    }

    /**
     * Whether a complete version exists.
     */
    public function hasVersion(string $prefix, string $sha256): bool
    {
        try {
            return $this->filesystem->fileExists($prefix.'/'.$sha256.'.elpx');
        } catch (FilesystemException) {
            return false;
        }
    }

    /**
     * Remove one version (original and extracted files). Never throws.
     */
    public function deleteVersion(string $prefix, string $sha256): void
    {
        try {
            $this->filesystem->delete($prefix.'/'.$sha256.'.elpx');
            $this->filesystem->deleteDirectory($prefix.'/'.$sha256);
        } catch (FilesystemException) {
            // Best effort: an orphan directory is harmless and invisible.
        }
    }

    /**
     * Remove a whole subtree (a package, a course, or the plugin root). Never throws.
     */
    public function deleteTree(string $prefix): void
    {
        try {
            $this->filesystem->deleteDirectory($prefix);
        } catch (FilesystemException) {
        }
    }

    /**
     * @return resource|null
     */
    public function readStream(string $key)
    {
        try {
            if (!$this->filesystem->fileExists($key)) {
                return null;
            }

            return $this->filesystem->readStream($key);
        } catch (FilesystemException) {
            return null;
        }
    }

    public function fileSize(string $key): ?int
    {
        try {
            return $this->filesystem->fileSize($key);
        } catch (FilesystemException) {
            return null;
        }
    }

    /**
     * Copy one entry, refusing to write more bytes than the central directory
     * declared: a ZIP whose headers lie about sizes is a bomb.
     */
    private function copyEntry(ZipArchive $zip, string $name, int $declaredSize, string $target): void
    {
        $source = $zip->getStream($name);
        if (false === $source) {
            throw new PackageException(PackageException::INVALID_ZIP);
        }

        $buffer = fopen('php://temp', 'w+b');
        if (false === $buffer) {
            fclose($source);

            throw new PackageException(PackageException::STORAGE);
        }

        try {
            $written = 0;
            while (!feof($source)) {
                $chunk = fread($source, self::CHUNK);
                if (false === $chunk) {
                    throw new PackageException(PackageException::INVALID_ZIP);
                }
                $written += \strlen($chunk);
                if ($written > $declaredSize) {
                    throw new PackageException(PackageException::TOO_LARGE);
                }
                fwrite($buffer, $chunk);
            }
            rewind($buffer);
            $this->filesystem->writeStream($target, $buffer);
        } finally {
            fclose($source);
            if (\is_resource($buffer)) {
                fclose($buffer);
            }
        }
    }
}
