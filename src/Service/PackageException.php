<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Service;

use RuntimeException;

/**
 * A package was rejected or could not be stored.
 *
 * The message is a translation key from lang/en_US.php, so pages can show it
 * to the user with $plugin->get_lang($e->getMessage()) without leaking paths.
 */
final class PackageException extends RuntimeException
{
    public const string INVALID_ZIP = 'error_invalid_zip';
    public const string LEGACY_ELP = 'error_legacy_elp';
    public const string NOT_EXELEARNING = 'error_not_exelearning';
    public const string MISSING_INDEX = 'error_missing_index';
    public const string UNSAFE_PATH = 'error_unsafe_path';
    public const string SYMLINK = 'error_symlink';
    public const string TOO_MANY_ENTRIES = 'error_too_many_entries';
    public const string TOO_LARGE = 'error_too_large';
    public const string COMPRESSION_RATIO = 'error_compression_ratio';
    public const string BAD_EXTENSION = 'error_bad_extension';
    public const string STORAGE = 'error_storage';
}
