<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Service;

use ZipArchive;

use const PATHINFO_EXTENSION;

/**
 * Validates an uploaded eXeLearning v4 package before anything is written.
 *
 * Accepts only .elpx (or .zip) archives with an ODE 2.0 content.xml and an
 * index.html at the root. Rejects legacy .elp (contentv3.xml), unsafe entry
 * names, symlinks, executable names, too many entries, oversized content and
 * suspicious compression ratios. The whole archive is rejected on the first
 * bad entry: nothing is extracted from a package that fails.
 */
final class PackageValidator
{
    /**
     * Below this size a high compression ratio is normal (text, blank images).
     */
    private const int RATIO_MIN_ENTRY_SIZE = 1_048_576;

    private const int UNIX_FILE_TYPE_MASK = 0o170000;
    private const int UNIX_SYMLINK = 0o120000;

    public function __construct(
        private readonly int $maxEntries = 10_000,
        private readonly int $maxUncompressedBytes = 1_073_741_824,
        private readonly int $maxCompressionRatio = 100,
    ) {}

    /**
     * @param string $zipPath      local path of the uploaded file
     * @param string $originalName file name as sent by the browser (for the extension check)
     *
     * @throws PackageException with a translation key as message
     */
    public function validate(string $zipPath, string $originalName): ValidatedPackage
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ('elp' === $extension) {
            throw new PackageException(PackageException::LEGACY_ELP);
        }
        if (!\in_array($extension, ['elpx', 'zip'], true)) {
            throw new PackageException(PackageException::BAD_EXTENSION);
        }

        $zip = new ZipArchive();
        if (true !== $zip->open($zipPath, ZipArchive::CHECKCONS | ZipArchive::RDONLY)) {
            throw new PackageException(PackageException::INVALID_ZIP);
        }

        try {
            $files = $this->checkEntries($zip, (int) filesize($zipPath));

            if (!isset($files['content.xml'])) {
                throw new PackageException(isset($files['contentv3.xml']) ? PackageException::LEGACY_ELP : PackageException::NOT_EXELEARNING);
            }

            $metadata = PackageMetadata::fromContentXml((string) $zip->getFromName('content.xml'));
            if (null === $metadata) {
                throw new PackageException(PackageException::NOT_EXELEARNING);
            }

            if (!isset($files['index.html'])) {
                throw new PackageException(PackageException::MISSING_INDEX);
            }
        } finally {
            $zip->close();
        }

        return new ValidatedPackage($metadata, $files, (string) hash_file('sha256', $zipPath));
    }

    /**
     * @return array<string, int> file entry name => declared uncompressed size
     */
    private function checkEntries(ZipArchive $zip, int $archiveSize): array
    {
        if ($zip->numFiles < 1) {
            throw new PackageException(PackageException::NOT_EXELEARNING);
        }
        if ($zip->numFiles > $this->maxEntries) {
            throw new PackageException(PackageException::TOO_MANY_ENTRIES);
        }

        $files = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i);
            if (false === $stat) {
                throw new PackageException(PackageException::INVALID_ZIP);
            }

            $name = (string) $stat['name'];
            if (!PackagePath::isSafe($name) || PackagePath::isForbiddenName($name)) {
                throw new PackageException(PackageException::UNSAFE_PATH);
            }

            $opsys = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
                && ZipArchive::OPSYS_UNIX === $opsys
                && self::UNIX_SYMLINK === (($attributes >> 16) & self::UNIX_FILE_TYPE_MASK)) {
                throw new PackageException(PackageException::SYMLINK);
            }

            if (str_ends_with($name, '/')) {
                continue;
            }

            $size = (int) $stat['size'];
            $compressed = max(1, (int) $stat['comp_size']);
            if ($size > self::RATIO_MIN_ENTRY_SIZE && intdiv($size, $compressed) > $this->maxCompressionRatio) {
                throw new PackageException(PackageException::COMPRESSION_RATIO);
            }

            $total += $size;
            if ($total > $this->maxUncompressedBytes) {
                throw new PackageException(PackageException::TOO_LARGE);
            }

            $files[$name] = $size;
        }

        if ($total > self::RATIO_MIN_ENTRY_SIZE && intdiv($total, max(1, $archiveSize)) > $this->maxCompressionRatio) {
            throw new PackageException(PackageException::COMPRESSION_RATIO);
        }

        return $files;
    }
}
