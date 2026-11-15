<?php

declare(strict_types=1);

namespace App\Booking\Domain\Model;

use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\Model\PerformanceSeat;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\Exception\ConflictException;
use App\Venue\Domain\Model\Zone;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One admission. Price, tariff label, seat and zone labels are snapshots taken
 * at sale time: later price or layout edits never change a sold ticket.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ticket')]
#[ORM\Index(name: 'idx_ticket_perf_status', columns: ['performance_id', 'status'])]
#[ORM\UniqueConstraint(name: 'uniq_ticket_active_seat', columns: ['active_seat_key'])]
class Ticket
{
    /**
     * Final safety net against double sales, maintained by MySQL: equals the
     * performance seat while the ticket is live, NULL otherwise. The unique index
     * allows many NULLs but only one live ticket per performance seat, even if
     * application code were wrong.
     *
     * The generated expression lives in the migration only: MySQL introspection
     * reports it as a plain BINARY(16), so mapping it that way keeps diffs empty.
     */
    #[ORM\Column(name: 'active_seat_key', type: Types::BINARY, length: 16, nullable: true, insertable: false, updatable: false, options: ['fixed' => true])]
    private mixed $activeSeatKey = null;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 16, enumType: TicketStatus::class)]
    private TicketStatus $status = TicketStatus::VALID;

    /** Random per-ticket value signed into the QR payload; rotating it revokes old QR codes. */
    #[ORM\Column(length: 32)]
    private string $qrNonce;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $checkedInAt = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $checkedInBy = null;

    #[ORM\Column(length: 80)]
    private string $tariffLabel;

    #[ORM\Column(length: 80)]
    private string $zoneLabel;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $seatLabel;

    #[ORM\Column]
    private bool $requiresProof;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'tickets')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Order $order,
        #[ORM\ManyToOne(targetEntity: Performance::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Performance $performance,
        #[ORM\ManyToOne(targetEntity: Zone::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Zone $zone,
        #[ORM\ManyToOne(targetEntity: Tariff::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Tariff $tariff,
        #[ORM\Column]
        private int $unitPriceCents,
        #[ORM\ManyToOne(targetEntity: PerformanceSeat::class)]
        #[ORM\JoinColumn(nullable: true)]
        private ?PerformanceSeat $performanceSeat = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->qrNonce = bin2hex(random_bytes(16));
        $this->tariffLabel = $tariff->label();
        $this->requiresProof = $tariff->requiresProofAtEntry();
        $this->zoneLabel = $zone->name();
        $this->seatLabel = $performanceSeat?->seat()->label();
        $order->addTicket($this);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function order(): Order
    {
        return $this->order;
    }

    public function performance(): Performance
    {
        return $this->performance;
    }

    public function performanceSeat(): ?PerformanceSeat
    {
        return $this->performanceSeat;
    }

    public function zone(): Zone
    {
        return $this->zone;
    }

    public function tariff(): Tariff
    {
        return $this->tariff;
    }

    public function unitPriceCents(): int
    {
        return $this->unitPriceCents;
    }

    public function tariffLabel(): string
    {
        return $this->tariffLabel;
    }

    public function zoneLabel(): string
    {
        return $this->zoneLabel;
    }

    public function seatLabel(): ?string
    {
        return $this->seatLabel;
    }

    public function requiresProof(): bool
    {
        return $this->requiresProof;
    }

    public function status(): TicketStatus
    {
        return $this->status;
    }

    public function qrNonce(): string
    {
        return $this->qrNonce;
    }

    public function checkedInAt(): ?DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function checkedInBy(): ?Uuid
    {
        return $this->checkedInBy;
    }

    public function checkIn(Uuid $by, DateTimeImmutable $now): void
    {
        if (TicketStatus::VALID !== $this->status) {
            throw ConflictException::because('TICKET_NOT_VALID', 'Ce billet n’est pas valide pour l’entrée.', ['status' => $this->status->value]);
        }
        $this->status = TicketStatus::CHECKED_IN;
        $this->checkedInAt = $now;
        $this->checkedInBy = $by;
    }

    /** Ticket of an order not paid yet (online checkout). */
    public function awaitPayment(): void
    {
        if (TicketStatus::VALID === $this->status && null === $this->checkedInAt) {
            $this->status = TicketStatus::PENDING;
        }
    }

    /** Payment confirmed: the ticket becomes admissible and claims its seat. */
    public function activate(): void
    {
        if (TicketStatus::PENDING === $this->status) {
            $this->status = TicketStatus::VALID;
        }
    }

    public function cancel(): void
    {
        if ($this->status->isLive() || TicketStatus::PENDING === $this->status) {
            $this->status = TicketStatus::CANCELLED;
        }
    }

    public function markRefunded(): void
    {
        if (TicketStatus::REFUNDED !== $this->status) {
            $this->status = TicketStatus::REFUNDED;
        }
    }
}
