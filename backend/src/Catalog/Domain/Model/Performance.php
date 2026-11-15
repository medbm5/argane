<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\ConflictException;
use App\Venue\Domain\Model\HallLayout;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A dated occurrence of an event, played in a given hall layout.
 * "Past" is derived from startsAt, never stored.
 */
#[ORM\Entity]
#[ORM\Table(name: 'performance')]
#[ORM\Index(name: 'idx_performance_starts', columns: ['starts_at'])]
#[ORM\Index(name: 'idx_performance_event', columns: ['event_id', 'starts_at'])]
#[ORM\Index(name: 'idx_performance_layout', columns: ['layout_id', 'starts_at'])]
class Performance
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 16, enumType: PerformanceStatus::class)]
    private PerformanceStatus $status = PerformanceStatus::SCHEDULED;

    /** Set when the inventory (one row per seat) has been generated. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cancelReason = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Event::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Event $event,
        #[ORM\ManyToOne(targetEntity: HallLayout::class)]
        #[ORM\JoinColumn(nullable: false)]
        private HallLayout $layout,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $startsAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $doorsAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $salesOpenAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $salesCloseAt,
    ) {
        $this->id = Uuid::v7();
        self::assertSchedule($startsAt, $doorsAt, $salesOpenAt, $salesCloseAt);
        if ($layout->venue() !== $event->venue()) {
            throw BusinessRuleException::because('PERFORMANCE_LAYOUT_MISMATCH', 'La configuration choisie n’appartient pas à la salle du spectacle.');
        }
        if (null !== $layout->archivedAt()) {
            throw BusinessRuleException::because('LAYOUT_ARCHIVED', 'Cette configuration de salle est archivée.');
        }
    }

    /**
     * Defaults: doors 30 min before, sales open now-ish, close at curtain time.
     */
    public static function schedule(Event $event, HallLayout $layout, DateTimeImmutable $startsAt, DateTimeImmutable $salesOpenAt): self
    {
        return new self($event, $layout, $startsAt, $startsAt->modify('-30 minutes'), $salesOpenAt, $startsAt);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function event(): Event
    {
        return $this->event;
    }

    public function layout(): HallLayout
    {
        return $this->layout;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function doorsAt(): DateTimeImmutable
    {
        return $this->doorsAt;
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->startsAt->modify(sprintf('+%d minutes', $this->event->durationMinutes()));
    }

    public function salesOpenAt(): DateTimeImmutable
    {
        return $this->salesOpenAt;
    }

    public function salesCloseAt(): DateTimeImmutable
    {
        return $this->salesCloseAt;
    }

    public function status(): PerformanceStatus
    {
        return $this->status;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function isCancelled(): bool
    {
        return PerformanceStatus::CANCELLED === $this->status;
    }

    public function isPast(DateTimeImmutable $now): bool
    {
        return $this->startsAt <= $now;
    }

    /** Status shown to people: PAST and SOLD_OUT are derived elsewhere/here. */
    public function displayStatus(DateTimeImmutable $now): string
    {
        if ($this->isCancelled()) {
            return PerformanceStatus::CANCELLED->value;
        }

        return $this->isPast($now) ? 'PAST' : $this->status->value;
    }

    /** Can a customer or the box office buy right now? */
    public function isOnSale(DateTimeImmutable $now): bool
    {
        return PerformanceStatus::ON_SALE === $this->status
            && $this->isPublished()
            && $this->salesOpenAt <= $now
            && $now < $this->salesCloseAt
            && $now < $this->startsAt;
    }

    /**
     * The box office keeps selling after online sales close, right up to curtain time
     * (walk-ins), as long as sales were opened and the date is not cancelled.
     */
    public function isOnSaleAtCounter(DateTimeImmutable $now): bool
    {
        return \in_array($this->status, [PerformanceStatus::ON_SALE, PerformanceStatus::SALES_CLOSED], true)
            && $this->isPublished()
            && $this->salesOpenAt <= $now
            && $now < $this->startsAt;
    }

    public function reschedule(DateTimeImmutable $startsAt, DateTimeImmutable $doorsAt, DateTimeImmutable $salesOpenAt, DateTimeImmutable $salesCloseAt): void
    {
        $this->assertNotCancelled();
        self::assertSchedule($startsAt, $doorsAt, $salesOpenAt, $salesCloseAt);
        $this->startsAt = $startsAt;
        $this->doorsAt = $doorsAt;
        $this->salesOpenAt = $salesOpenAt;
        $this->salesCloseAt = $salesCloseAt;
    }

    public function changeLayout(HallLayout $layout): void
    {
        if ($this->isPublished()) {
            throw ConflictException::because('PERFORMANCE_PUBLISHED', 'La configuration ne peut plus changer : la représentation est publiée.');
        }
        if ($layout->venue() !== $this->event->venue() || null !== $layout->archivedAt()) {
            throw BusinessRuleException::because('PERFORMANCE_LAYOUT_MISMATCH', 'Configuration de salle invalide pour ce spectacle.');
        }
        $this->layout = $layout;
    }

    public function openSales(DateTimeImmutable $now): void
    {
        $this->assertNotCancelled();
        if (!$this->isPublished()) {
            throw ConflictException::because('PERFORMANCE_NOT_PUBLISHED', 'Publiez la représentation avant d’ouvrir les ventes.');
        }
        if ($this->isPast($now)) {
            throw ConflictException::because('PERFORMANCE_PAST', 'Cette représentation a déjà eu lieu.');
        }
        $this->status = PerformanceStatus::ON_SALE;
    }

    public function closeSales(): void
    {
        $this->assertNotCancelled();
        $this->status = PerformanceStatus::SALES_CLOSED;
    }

    public function cancel(string $reason, DateTimeImmutable $now): void
    {
        if ($this->isCancelled()) {
            return;
        }
        $this->status = PerformanceStatus::CANCELLED;
        $this->cancelledAt = $now;
        $this->cancelReason = $reason;
    }

    /** Internal: called by the inventory generator once rows exist. */
    public function markPublished(DateTimeImmutable $now): void
    {
        $this->publishedAt ??= $now;
    }

    public function assertNotCancelled(): void
    {
        if ($this->isCancelled()) {
            throw ConflictException::because('PERFORMANCE_CANCELLED', 'Cette représentation est annulée.');
        }
    }

    private static function assertSchedule(DateTimeImmutable $startsAt, DateTimeImmutable $doorsAt, DateTimeImmutable $salesOpenAt, DateTimeImmutable $salesCloseAt): void
    {
        if ($doorsAt > $startsAt) {
            throw BusinessRuleException::because('PERFORMANCE_INVALID_SCHEDULE', 'L’ouverture des portes doit précéder le début.');
        }
        if ($salesOpenAt >= $salesCloseAt) {
            throw BusinessRuleException::because('PERFORMANCE_INVALID_SCHEDULE', 'La fermeture des ventes doit suivre leur ouverture.');
        }
        if ($salesCloseAt > $startsAt->modify('+1 hour')) {
            throw BusinessRuleException::because('PERFORMANCE_INVALID_SCHEDULE', 'Les ventes doivent fermer au plus tard une heure après le début.');
        }
    }
}
