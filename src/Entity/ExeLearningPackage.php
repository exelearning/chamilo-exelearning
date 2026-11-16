<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Entity;

use Chamilo\CoreBundle\Entity\Course;
use Chamilo\CoreBundle\Entity\Session;
use Chamilo\CoreBundle\Entity\User;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;

/**
 * An eXeLearning package in a course (and optionally a session).
 *
 * The files live in the plugins storage under
 * ExeLearning/c<course>/p<id>/<contentHash>; this row points at the current version.
 */
#[ORM\Entity]
#[ORM\Table(name: 'plugin_exelearning_package')]
#[ORM\Index(name: 'idx_exelearning_course_session', columns: ['c_id', 'session_id'])]
class ExeLearningPackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(name: 'c_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Course $course;

    #[ORM\ManyToOne(targetEntity: Session::class)]
    #[ORM\JoinColumn(name: 'session_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Session $session = null;

    /**
     * Creator. Kept as NULL when the user is hard-deleted: the package belongs to the course.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'title', type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'language', type: 'string', length: 20, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(name: 'license', type: 'string', length: 255, nullable: true)]
    private ?string $license = null;

    #[ORM\Column(name: 'package_version', type: 'string', length: 50, nullable: true)]
    private ?string $packageVersion = null;

    /**
     * SHA-256 of the current .elpx; also the name of its content directory.
     */
    #[ORM\Column(name: 'content_hash', type: 'string', length: 64)]
    private string $contentHash = '';

    #[ORM\Column(name: 'original_filename', type: 'string', length: 255)]
    private string $originalFilename = '';

    #[ORM\Column(name: 'file_size', type: 'bigint')]
    private string $fileSize = '0';

    #[ORM\Column(name: 'visible', type: 'boolean')]
    private bool $visible = true;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private DateTime $updatedAt;

    public function __construct(Course $course, ?Session $session, ?User $user)
    {
        $this->course = $course;
        $this->session = $session;
        $this->user = $user;
        $this->createdAt = new DateTime('now', new DateTimeZone('UTC'));
        $this->updatedAt = new DateTime('now', new DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): Course
    {
        return $this->course;
    }

    public function getCourseId(): int
    {
        return (int) $this->course->getId();
    }

    public function getSessionId(): int
    {
        return (int) $this->session?->getId();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = mb_substr(trim($title), 0, 255);
        $this->touch();

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function getPackageVersion(): ?string
    {
        return $this->packageVersion;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getFileSize(): int
    {
        return (int) $this->fileSize;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;
        $this->touch();

        return $this;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Point the package at a newly stored version and copy its metadata.
     */
    public function setVersion(
        string $contentHash,
        string $originalFilename,
        int $fileSize,
        string $description,
        string $language,
        string $license,
        string $packageVersion,
    ): self {
        $this->contentHash = $contentHash;
        $this->originalFilename = mb_substr($originalFilename, 0, 255);
        $this->fileSize = (string) $fileSize;
        $this->description = '' === $description ? null : $description;
        $this->language = '' === $language ? null : $language;
        $this->license = '' === $license ? null : $license;
        $this->packageVersion = '' === $packageVersion ? null : $packageVersion;
        $this->touch();

        return $this;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTime('now', new DateTimeZone('UTC'));
    }
}
