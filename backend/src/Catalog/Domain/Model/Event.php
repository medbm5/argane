<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Exception\BusinessRuleException;
use App\Venue\Domain\Model\Venue;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;

/**
 * A show in the season (a play, a concert…). Performances are its dated occurrences.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event')]
#[ORM\UniqueConstraint(name: 'uniq_event_slug', columns: ['venue_id', 'slug'])]
#[ORM\Index(name: 'idx_event_status', columns: ['status', 'category'])]
class Event
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 140)]
    private string $slug;

    #[ORM\Column(length: 16, enumType: EventStatus::class)]
    private EventStatus $status = EventStatus::DRAFT;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $posterPath = null;

    /** Minimum age in years; null means "tout public". */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $ageRating = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Venue::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Venue $venue,
        #[ORM\Column(length: 140)]
        private string $title,
        #[ORM\Column(length: 16, enumType: EventCategory::class)]
        private EventCategory $category,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $durationMinutes,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $createdBy,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $createdAt,
        ?string $slug = null,
    ) {
        $this->id = Uuid::v7();
        $this->slug = $slug ?? self::slugify($title);
        $this->assertDuration($durationMinutes);
    }

    public static function slugify(string $title): string
    {
        return (new AsciiSlugger('fr'))->slug($title)->lower()->toString();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function venue(): Venue
    {
        return $this->venue;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function subtitle(): ?string
    {
        return $this->subtitle;
    }

    public function category(): EventCategory
    {
        return $this->category;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function posterPath(): ?string
    {
        return $this->posterPath;
    }

    public function durationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function ageRating(): ?int
    {
        return $this->ageRating;
    }

    public function status(): EventStatus
    {
        return $this->status;
    }

    public function isPublished(): bool
    {
        return EventStatus::PUBLISHED === $this->status;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function createdBy(): ?Uuid
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function describe(
        string $title,
        ?string $subtitle,
        EventCategory $category,
        string $description,
        int $durationMinutes,
        ?int $ageRating,
        ?string $posterPath,
    ): void {
        $this->assertDuration($durationMinutes);
        if (null !== $ageRating && ($ageRating < 0 || $ageRating > 21)) {
            throw BusinessRuleException::because('EVENT_INVALID_AGE_RATING', 'L’âge minimum doit être compris entre 0 et 21 ans.');
        }
        $this->title = $title;
        $this->subtitle = '' === $subtitle ? null : $subtitle;
        $this->category = $category;
        $this->description = trim($description);
        $this->durationMinutes = $durationMinutes;
        $this->ageRating = $ageRating;
        $this->posterPath = $posterPath;
    }

    /**
     * A published event shows in the programme (with its published performances).
     */
    public function publish(DateTimeImmutable $now): void
    {
        if (EventStatus::ARCHIVED === $this->status) {
            throw BusinessRuleException::because('EVENT_ARCHIVED', 'Un spectacle archivé ne peut pas être publié.');
        }
        if ('' === trim($this->description)) {
            throw BusinessRuleException::because('EVENT_INCOMPLETE', 'Ajoutez une description avant de publier le spectacle.');
        }
        $this->status = EventStatus::PUBLISHED;
        $this->publishedAt ??= $now;
    }

    public function unpublish(): void
    {
        if (EventStatus::PUBLISHED === $this->status) {
            $this->status = EventStatus::DRAFT;
        }
    }

    public function archive(): void
    {
        $this->status = EventStatus::ARCHIVED;
    }

    private function assertDuration(int $minutes): void
    {
        if ($minutes < 5 || $minutes > 600) {
            throw BusinessRuleException::because('EVENT_INVALID_DURATION', 'La durée doit être comprise entre 5 minutes et 10 heures.');
        }
    }
}
