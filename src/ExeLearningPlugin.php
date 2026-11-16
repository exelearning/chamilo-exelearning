<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

use Chamilo\CoreBundle\Framework\Container;
use Chamilo\CourseBundle\Entity\CTool;
use Chamilo\PluginBundle\ExeLearning\Entity\ExeLearningPackage;
use Chamilo\PluginBundle\ExeLearning\Service\AccessPolicy;
use Chamilo\PluginBundle\ExeLearning\Service\ContentToken;
use Chamilo\PluginBundle\ExeLearning\Service\EditorBootstrap;
use Chamilo\PluginBundle\ExeLearning\Service\PackageException;
use Chamilo\PluginBundle\ExeLearning\Service\PackageStorage;
use Chamilo\PluginBundle\ExeLearning\Service\PackageValidator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * eXeLearning course plugin: upload, view and edit eXeLearning v4 packages.
 *
 * Thin glue between Chamilo and the services in src/Service: it gathers
 * context (course, session, roles, settings) and delegates every decision.
 */
class ExeLearningPlugin extends Plugin
{
    public const string VERSION = '0.1.0';
    public const string TABLE_PACKAGE = 'plugin_exelearning_package';

    /**
     * Same token for every form of the plugin, see Security::get_existing_token().
     */
    public const string CSRF_PREFIX = 'exelearning';

    /**
     * Untyped in the parent Plugin class, so no native type here.
     *
     * @var bool
     */
    public $isCoursePlugin = true;

    /**
     * @var bool
     */
    public $addCourseTool = true;

    protected function __construct()
    {
        parent::__construct(
            self::VERSION,
            'eXeLearning',
            [
                'editor_enabled' => 'boolean',
                'max_upload_size_mb' => 'text',
                'edit_roles' => [
                    'type' => 'select',
                    'options' => [
                        AccessPolicy::ROLES_TEACHERS => 'edit_roles_teachers',
                        AccessPolicy::ROLES_TEACHERS_COACHES => 'edit_roles_teachers_coaches',
                        AccessPolicy::ROLES_ADMINS => 'edit_roles_admins',
                    ],
                    'translate_options' => true,
                ],
            ]
        );
    }

    public static function create(): self
    {
        static $instance = null;

        return $instance ??= new self();
    }

    public function get_name(): string
    {
        return 'ExeLearning';
    }

    /**
     * Create the plugin table. Idempotent.
     */
    public function install(): void
    {
        $em = Database::getManager();
        if ($em->getConnection()->createSchemaManager()->tablesExist([self::TABLE_PACKAGE])) {
            return;
        }

        (new SchemaTool($em))->createSchema([$em->getClassMetadata(ExeLearningPackage::class)]);
    }

    /**
     * Drop the table, remove every stored package and the course tool links.
     */
    public function uninstall(): void
    {
        $em = Database::getManager();
        if ($em->getConnection()->createSchemaManager()->tablesExist([self::TABLE_PACKAGE])) {
            (new SchemaTool($em))->dropSchema([$em->getClassMetadata(ExeLearningPackage::class)]);
        }

        $this->storage()->deleteTree(PackageStorage::ROOT);
        $this->uninstall_course_fields_in_all_courses();
    }

    public function storage(): PackageStorage
    {
        return new PackageStorage(Container::getPluginsFileSystem());
    }

    public function staticEditorDir(): string
    {
        return __DIR__.'/../dist/static';
    }

    /**
     * Embedded editor on (setting not "false") and its bundle shipped.
     */
    public function isEditorEnabled(): bool
    {
        return 'false' !== (string) $this->get('editor_enabled') && EditorBootstrap::isAvailable($this->staticEditorDir());
    }

    /**
     * Upload limit in bytes: the setting, capped by PHP's own limits.
     */
    public function maxUploadBytes(): int
    {
        $limits = array_filter([
            self::iniBytes((string) ini_get('upload_max_filesize')),
            self::iniBytes((string) ini_get('post_max_size')),
            max(0, (int) $this->get('max_upload_size_mb')) * 1_048_576,
        ]);

        return [] === $limits ? 0 : min($limits);
    }

    /**
     * Whether the current user may create/edit/manage packages here.
     */
    public function canEdit(): bool
    {
        if (api_is_anonymous()) {
            return false;
        }

        return AccessPolicy::canEdit(
            api_is_platform_admin(),
            (bool) api_is_allowed_to_edit(),
            (bool) api_is_allowed_to_edit(false, true),
            (string) ($this->get('edit_roles') ?: AccessPolicy::ROLES_TEACHERS)
        );
    }

    /**
     * Visibility of the course tool (teacher's eye icon on the course home).
     * Core only enforces it in the course-home listing, so pages check it here.
     */
    public function isToolVisible(int $courseId): bool
    {
        $tool = Database::getManager()->getRepository(CTool::class)->findOneBy([
            'course' => api_get_course_entity($courseId),
            'title' => $this->get_name(),
        ]);

        return null === $tool || $tool->getVisibility();
    }

    public function findPackage(int $id): ?ExeLearningPackage
    {
        return $id > 0 ? Database::getManager()->find(ExeLearningPackage::class, $id) : null;
    }

    /**
     * The package if the current user may open it from the current course
     * context (after api_protect_course_script()), null otherwise.
     */
    public function packageForView(int $id): ?ExeLearningPackage
    {
        $package = $this->findPackage($id);
        if (null === $package) {
            return null;
        }

        $courseId = api_get_course_int_id();
        $inContext = AccessPolicy::isInContext($package->getCourseId(), $package->getSessionId(), $courseId, api_get_session_id());
        $canEdit = $this->canEdit();

        return AccessPolicy::canView($canEdit, $package->isVisible(), $canEdit || $this->isToolVisible($courseId), $inContext) ? $package : null;
    }

    /**
     * The package if the current user may change it from the current context.
     */
    public function packageForEdit(int $id): ?ExeLearningPackage
    {
        $package = $this->findPackage($id);
        if (null === $package || !$this->canEdit()) {
            return null;
        }

        return AccessPolicy::ownsPackage($package->getCourseId(), $package->getSessionId(), api_get_course_int_id(), api_get_session_id()) ? $package : null;
    }

    /**
     * Packages visible from the current course/session context.
     *
     * @return ExeLearningPackage[]
     */
    public function packagesInContext(int $courseId, int $sessionId): array
    {
        $qb = Database::getManager()->createQueryBuilder()
            ->select('p')
            ->from(ExeLearningPackage::class, 'p')
            ->where('IDENTITY(p.course) = :course')
            ->setParameter('course', $courseId)
            ->orderBy('p.title', 'ASC')
        ;
        if ($sessionId > 0) {
            $qb->andWhere('p.session IS NULL OR IDENTITY(p.session) = :session')->setParameter('session', $sessionId);
        } else {
            $qb->andWhere('p.session IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Validate and store an uploaded or saved .elpx as a new package.
     *
     * @throws PackageException
     */
    public function createPackage(string $tmpPath, string $originalName, string $title, int $courseId, int $sessionId): ExeLearningPackage
    {
        $this->assertSize($tmpPath);
        $validated = (new PackageValidator())->validate($tmpPath, $originalName);

        $em = Database::getManager();
        $package = new ExeLearningPackage(
            api_get_course_entity($courseId),
            $sessionId > 0 ? api_get_session_entity($sessionId) : null,
            api_get_user_entity()
        );
        $package->setTitle('' !== trim($title) ? $title : ('' !== $validated->metadata->title ? $validated->metadata->title : pathinfo($originalName, \PATHINFO_FILENAME)));
        $em->persist($package);
        $em->flush();

        $prefix = PackageStorage::packagePrefix($courseId, (int) $package->getId());

        try {
            $this->storage()->store($tmpPath, $validated, $prefix);
        } catch (PackageException $e) {
            $em->remove($package);
            $em->flush();

            throw $e;
        }

        $meta = $validated->metadata;
        $package->setVersion($validated->sha256, $originalName, (int) filesize($tmpPath), $meta->description, $meta->language, $meta->license, $meta->version);
        $em->flush();

        return $package;
    }

    /**
     * Replace a package's content atomically: the new version is stored in
     * full before the row switches to it, and the old version is removed only
     * after the switch. Returns false on a concurrent change ($expectedHash
     * no longer current).
     *
     * @throws PackageException
     */
    public function replacePackage(ExeLearningPackage $package, string $tmpPath, string $originalName, string $expectedHash): bool
    {
        $this->assertSize($tmpPath);
        $validated = (new PackageValidator())->validate($tmpPath, $originalName);
        $prefix = PackageStorage::packagePrefix($package->getCourseId(), (int) $package->getId());
        $storage = $this->storage();
        $storage->store($tmpPath, $validated, $prefix);

        $em = Database::getManager();
        $previous = null;
        $switched = $em->wrapInTransaction(function () use ($em, $package, $validated, $tmpPath, $originalName, $expectedHash, &$previous): bool {
            $locked = $em->find(ExeLearningPackage::class, $package->getId(), LockMode::PESSIMISTIC_WRITE);
            if (null === $locked || !hash_equals($locked->getContentHash(), $expectedHash)) {
                return false;
            }
            $previous = $locked->getContentHash();
            $meta = $validated->metadata;
            $locked->setVersion($validated->sha256, $originalName, (int) filesize($tmpPath), $meta->description, $meta->language, $meta->license, $meta->version);

            return true;
        });

        if (!$switched) {
            if ($validated->sha256 !== $package->getContentHash()) {
                $storage->deleteVersion($prefix, $validated->sha256);
            }

            return false;
        }

        if (null !== $previous && $previous !== $validated->sha256) {
            $storage->deleteVersion($prefix, $previous);
        }

        return true;
    }

    public function deletePackage(ExeLearningPackage $package): void
    {
        $this->storage()->deleteTree(PackageStorage::packagePrefix($package->getCourseId(), (int) $package->getId()));
        $em = Database::getManager();
        $em->remove($package);
        $em->flush();
    }

    public function contentToken(): ContentToken
    {
        $secret = (string) ($_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? '');
        if ('' === $secret) {
            throw new RuntimeException('APP_SECRET is not configured.');
        }

        return new ContentToken($secret);
    }

    /**
     * URL of a plugin script with the current course context appended.
     *
     * @param array<string, int|string> $params
     */
    public function url(string $script, array $params = []): string
    {
        $query = http_build_query($params);

        return api_get_path(WEB_PLUGIN_PATH).$this->get_name().'/'.$script.'?'.api_get_cidreq().('' !== $query ? '&'.$query : '');
    }

    /**
     * URL of a static file of the plugin, versioned by its mtime so browsers
     * never keep a stale copy (the web server caches .js/.css for days).
     */
    public function assetUrl(string $path): string
    {
        return api_get_path(WEB_PLUGIN_PATH).$this->get_name().'/'.$path.'?v='.(int) @filemtime(__DIR__.'/../'.$path);
    }

    /**
     * Translated plugin string, HTML-escaped.
     */
    public function e(string $key): string
    {
        return htmlspecialchars((string) $this->get_lang($key), \ENT_QUOTES, 'UTF-8');
    }

    /**
     * Human-readable size (Chamilo 3 has no shared helper for it).
     */
    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) max(0, $bytes);
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            ++$unit;
        }

        return (0 === $unit ? (string) (int) $size : number_format($size, 1)).' '.$units[$unit];
    }

    private function assertSize(string $tmpPath): void
    {
        $max = $this->maxUploadBytes();
        if ($max > 0 && (int) filesize($tmpPath) > $max) {
            throw new PackageException(PackageException::TOO_LARGE);
        }
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ('' === $value) {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1_073_741_824,
            'm' => $number * 1_048_576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
