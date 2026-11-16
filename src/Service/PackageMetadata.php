<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Service;

use DOMDocument;
use DOMXPath;

use const LIBXML_NOBLANKS;
use const LIBXML_NONET;

/**
 * Metadata read from a package's content.xml (ODE 2.0). Read-only: the
 * package itself is never rewritten.
 */
final readonly class PackageMetadata
{
    public function __construct(
        public string $title,
        public string $description,
        public string $language,
        public string $license,
        public string $version,
    ) {}

    /**
     * Parse content.xml. Returns null when it is not an ODE document.
     *
     * Entities and external DTDs are never loaded (no LIBXML_NOENT or
     * LIBXML_DTDLOAD; LIBXML_NONET), so the DOCTYPE in content.xml is inert.
     */
    public static function fromContentXml(string $xml): ?self
    {
        if ('' === trim($xml)) {
            return null;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || null === $document->documentElement || 'ode' !== $document->documentElement->localName) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $read = static function (string $container, string $key) use ($xpath): string {
            $query = \sprintf(
                "//*[local-name()='%s']/*[*[local-name()='key' and normalize-space()='%s']]/*[local-name()='value']",
                $container,
                $key
            );
            $node = $xpath->query($query)?->item(0);

            return null === $node ? '' : trim($node->textContent);
        };

        return new self(
            mb_substr($read('odeProperties', 'pp_title'), 0, 255),
            $read('odeProperties', 'pp_description'),
            mb_substr($read('odeProperties', 'pp_lang'), 0, 20),
            mb_substr($read('odeProperties', 'pp_license'), 0, 255),
            mb_substr($read('odeResources', 'exe_version'), 0, 50),
        );
    }
}
