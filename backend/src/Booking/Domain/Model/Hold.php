<?php

declare(strict_types=1);

namespace App\Booking\Domain\Model;

use App\Catalog\Domain\Model\Performance;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\SalesChannel;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A temporary reservation of seats (rows in performance_seat pointing at this hold)
 * and standing places (StandingHoldLine) while the buyer pays.
 * Expiry is lazy: an ACTIVE hold past expiresAt is treated as expired everywhere.
 */
#[ORM\Entity]
#[ORM\Table(name: 'hold')]
#[ORM\Index(name: 'idx_hold_user_perf', columns: ['user_id', 'performance_id', 'status'])]
#[ORM\Index(name: 'idx_hold_status_expiry', columns: ['status', 'expires_at'])]
class Hold
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 16, enumType: HoldStatus::class)]
    private HoldStatus $status = HoldStatus::ACTIVE;

    /** @var Collection<int, StandingHoldLine> */
    #[ORM\OneToMany(targetEntity: StandingHoldLine::class, mappedBy: 'hold', cascade: ['persist'], orphanRemoval: true)]
    private Collection $standingLines;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Performance::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Performance $performance,
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $userId,
        #[ORM\Column(length: 16, enumType: SalesChannel::class)]
        private SalesChannel $channel,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $expiresAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $createdAt,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->standingLines = new ArrayCollection();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function performance(): Performance
    {
        return $this->performance;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function channel(): SalesChannel
    {
        return $this->channel;
    }

    public function status(): HoldStatus
    {
        return $this->status;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(DateTimeImmutable $now): bool
    {
        return HoldStatus::ACTIVE === $this->status && $this->expiresAt > $now;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return HoldStatus::EXPIRED === $this->status || (HoldStatus::ACTIVE === $this->status && $this->expiresAt <= $now);
    }

    public function addStandingLine(StandingHoldLine $line): void
    {
        $this->standingLines->add($line);
    }

    /**
     * @return list<StandingHoldLine>
     */
    public function standingLines(): array
    {
        return array_values($this->standingLines->toArray());
    }

    public function assertActive(DateTimeImmutable $now): void
    {
        if (!$this->isActive($now)) {
            throw ConflictException::because('HOLD_EXPIRED', 'Votre réservation a expiré. Les places ont été libérées : choisissez-les de nouveau.', ['holdId' => $this->id->toRfc4122()]);
        }
    }

    public function release(): void
    {
        if (HoldStatus::ACTIVE === $this->status) {
            $this->status = HoldStatus::RELEASED;
        }
    }

    public function expire(): void
    {
        if (HoldStatus::ACTIVE === $this->status) {
            $this->status = HoldStatus::EXPIRED;
        }
    }

    public function convert(): void
    {
        $this->status = HoldStatus::CONVERTED;
    }

    /** Extend expiry (e.g. while Stripe confirms a payment); never shortens. */
    public function extendUntil(DateTimeImmutable $expiresAt): void
    {
        if ($expiresAt > $this->expiresAt) {
            $this->expiresAt = $expiresAt;
        }
    }
}
