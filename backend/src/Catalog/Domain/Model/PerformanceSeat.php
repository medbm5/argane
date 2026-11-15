<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Venue\Domain\Model\Seat;
use App\Venue\Domain\Model\Zone;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Inventory: one row per seat per performance.
 *
 * Holds and sales change these rows with conditional atomic UPDATEs (Booking module),
 * never by loading and saving the entity, so two buyers can never both win a seat.
 * A HELD row whose holdExpiresAt is in the past counts as available everywhere.
 */
#[ORM\Entity]
#[ORM\Table(name: 'performance_seat')]
#[ORM\UniqueConstraint(name: 'uniq_perf_seat', columns: ['performance_id', 'seat_id'])]
#[ORM\Index(name: 'idx_perf_seat_status', columns: ['performance_id', 'status'])]
#[ORM\Index(name: 'idx_perf_seat_hold', columns: ['hold_id'])]
class PerformanceSeat
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 16, enumType: SeatStatus::class)]
    private SeatStatus $status;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $holdId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $holdExpiresAt = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $ticketId = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Performance::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Performance $performance,
        #[ORM\ManyToOne(targetEntity: Seat::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Seat $seat,
        #[ORM\ManyToOne(targetEntity: Zone::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Zone $zone,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $updatedAt,
    ) {
        $this->id = Uuid::v7();
        $this->status = $seat->isBlocked() ? SeatStatus::BLOCKED : SeatStatus::AVAILABLE;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function performance(): Performance
    {
        return $this->performance;
    }

    public function seat(): Seat
    {
        return $this->seat;
    }

    public function zone(): Zone
    {
        return $this->zone;
    }

    public function status(): SeatStatus
    {
        return $this->status;
    }

    public function holdId(): ?Uuid
    {
        return $this->holdId;
    }

    public function holdExpiresAt(): ?DateTimeImmutable
    {
        return $this->holdExpiresAt;
    }

    public function ticketId(): ?Uuid
    {
        return $this->ticketId;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Effective status, treating expired holds as available (lazy expiry). */
    public function effectiveStatus(DateTimeImmutable $now): SeatStatus
    {
        if (SeatStatus::HELD === $this->status && null !== $this->holdExpiresAt && $this->holdExpiresAt <= $now) {
            return SeatStatus::AVAILABLE;
        }

        return $this->status;
    }
}
