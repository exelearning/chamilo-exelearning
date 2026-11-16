<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Service;

/**
 * The single rule for a relative path inside a package: validate, never rewrite.
 *
 * Shared by the ZIP validator (entry names) and the content endpoint (request
 * paths), so a name accepted at upload is exactly a name that can be served.
 */
final class PackagePath
{
    /**
     * File names that must never land in storage, even though storage is not
     * executed: defence in depth if the plugins filesystem is ever exposed.
     */
    private const string FORBIDDEN_NAME = '/(^\.ht|^\.user\.ini$|\.(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp)$)/i';

    /**
     * Whether $path is a safe relative path: no empty, "." or ".." segment, no
     * backslash, NUL or control character, no leading slash or drive letter.
     *
     * A trailing slash (ZIP directory entry) is accepted.
     */
    public static function isSafe(string $path): bool
    {
        if ('' === $path || \strlen($path) > 1024) {
            return false;
        }

        if (1 === preg_match('/[\x00-\x1F\x7F\\\]/', $path) || str_starts_with($path, '/') || 1 === preg_match('/^[A-Za-z]:/', $path)) {
            return false;
        }

        $segments = explode('/', str_ends_with($path, '/') ? substr($path, 0, -1) : $path);
        foreach ($segments as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                return false;
            }
        }

        return 1 !== preg_match('/^[a-z][a-z0-9+.-]*:/i', $path);
    }

    /**
     * Whether the base name of $path is on the executable/server-config deny list.
     */
    public static function isForbiddenName(string $path): bool
    {
        return 1 === preg_match(self::FORBIDDEN_NAME, basename($path));
    }
}
