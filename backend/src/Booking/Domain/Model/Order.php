<?php

declare(strict_types=1);

namespace App\Booking\Domain\Model;

use App\Catalog\Domain\Model\Performance;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Money;
use App\Shared\Domain\SalesChannel;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'customer_order')]
#[ORM\UniqueConstraint(name: 'uniq_order_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_order_customer', columns: ['customer_id', 'created_at'])]
#[ORM\Index(name: 'idx_order_perf_status', columns: ['performance_id', 'status'])]
#[ORM\Index(name: 'idx_order_status_created', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'idx_order_paid', columns: ['paid_at', 'channel', 'status'])]
#[ORM\Index(name: 'idx_order_cancelled', columns: ['cancelled_at', 'status'])]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 24, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PENDING_PAYMENT;

    #[ORM\Column]
    private int $totalCents = 0;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $walkInName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $walkInEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    /** @var Collection<int, Ticket> */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'order', cascade: ['persist'])]
    private Collection $tickets;

    public function __construct(
        #[ORM\Column(length: 20)]
        private string $reference,
        #[ORM\ManyToOne(targetEntity: Performance::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Performance $performance,
        #[ORM\Column(length: 16, enumType: SalesChannel::class)]
        private SalesChannel $channel,
        #[ORM\Column(length: 3)]
        private string $currency,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $createdAt,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $customerId = null,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $sellerId = null,
        #[ORM\ManyToOne(targetEntity: Hold::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Hold $hold = null,
    ) {
        $this->id = Uuid::v7();
        $this->currency = strtoupper($currency);
        $this->tickets = new ArrayCollection();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function performance(): Performance
    {
        return $this->performance;
    }

    public function channel(): SalesChannel
    {
        return $this->channel;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function total(): Money
    {
        return Money::of($this->totalCents, $this->currency);
    }

    public function totalCents(): int
    {
        return $this->totalCents;
    }

    public function customerId(): ?Uuid
    {
        return $this->customerId;
    }

    public function sellerId(): ?Uuid
    {
        return $this->sellerId;
    }

    public function hold(): ?Hold
    {
        return $this->hold;
    }

    public function walkInName(): ?string
    {
        return $this->walkInName;
    }

    public function walkInEmail(): ?string
    {
        return $this->walkInEmail;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function paidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function forWalkIn(string $name, ?string $email): void
    {
        $this->walkInName = $name;
        $this->walkInEmail = null === $email || '' === trim($email) ? null : mb_strtolower(trim($email));
    }

    public function annotate(?string $note): void
    {
        $this->note = $note;
    }

    public function addTicket(Ticket $ticket): void
    {
        if (OrderStatus::PENDING_PAYMENT !== $this->status) {
            throw ConflictException::because('ORDER_NOT_EDITABLE', 'Cette commande ne peut plus être modifiée.');
        }
        $this->tickets->add($ticket);
        $this->totalCents += $ticket->unitPriceCents();
    }

    /**
     * @return list<Ticket>
     */
    public function tickets(): array
    {
        return array_values($this->tickets->toArray());
    }

    public function markPaid(DateTimeImmutable $now): void
    {
        $this->transitionTo(OrderStatus::PAID);
        $this->paidAt = $now;
    }

    public function markExpired(): void
    {
        $this->transitionTo(OrderStatus::EXPIRED);
    }

    public function markFailed(): void
    {
        $this->transitionTo(OrderStatus::FAILED);
    }

    public function retryPayment(): void
    {
        $this->transitionTo(OrderStatus::PENDING_PAYMENT);
    }

    public function cancel(DateTimeImmutable $now): void
    {
        $this->transitionTo(OrderStatus::CANCELLED);
        $this->cancelledAt = $now;
    }

    public function markRefunded(DateTimeImmutable $now, bool $partial = false): void
    {
        $this->transitionTo($partial ? OrderStatus::PARTIALLY_REFUNDED : OrderStatus::REFUNDED);
        $this->cancelledAt ??= $now;
    }

    private function transitionTo(OrderStatus $target): void
    {
        if ($this->status === $target) {
            return;
        }
        if (!$this->status->canBecome($target)) {
            throw ConflictException::because('ORDER_INVALID_TRANSITION', sprintf('Une commande « %s » ne peut pas passer à « %s ».', $this->status->value, $target->value), ['reference' => $this->reference]);
        }
        $this->status = $target;
    }
}
