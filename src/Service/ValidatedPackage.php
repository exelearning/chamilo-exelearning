<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Service;

/**
 * Result of PackageValidator::validate(): what may be extracted, and its identity.
 */
final readonly class ValidatedPackage
{
    /**
     * @param array<string, int> $files  safe file entry name => declared uncompressed size
     * @param string             $sha256 hash of the archive bytes, used as the content directory name
     */
    public function __construct(
        public PackageMetadata $metadata,
        public array $files,
        public string $sha256,
    ) {}
}
