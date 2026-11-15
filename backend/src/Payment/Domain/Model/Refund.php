<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

use App\Booking\Domain\Model\Order;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Money returned to a customer. The refund id doubles as the Stripe
 * idempotency key, so retries never refund twice.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refund')]
#[ORM\Index(name: 'idx_refund_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_refund_status', columns: ['status'])]
class Refund
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 20, enumType: RefundStatus::class)]
    private RefundStatus $status = RefundStatus::PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerRef = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $processedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $failureReason = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Order $order,
        #[ORM\ManyToOne(targetEntity: Payment::class)]
        #[ORM\JoinColumn(nullable: true)]
        private ?Payment $payment,
        #[ORM\Column]
        private int $amountCents,
        #[ORM\Column(length: 255)]
        private string $reason,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $requestedBy,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function idempotencyKey(): string
    {
        return 'refund-'.$this->id->toRfc4122();
    }

    public function order(): Order
    {
        return $this->order;
    }

    public function payment(): ?Payment
    {
        return $this->payment;
    }

    public function amountCents(): int
    {
        return $this->amountCents;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function requestedBy(): ?Uuid
    {
        return $this->requestedBy;
    }

    public function status(): RefundStatus
    {
        return $this->status;
    }

    public function providerRef(): ?string
    {
        return $this->providerRef;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function processedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function awaitCounter(): void
    {
        $this->status = RefundStatus::AWAITING_COUNTER;
    }

    public function succeed(?string $providerRef, DateTimeImmutable $now): void
    {
        $this->status = RefundStatus::SUCCEEDED;
        $this->providerRef = $providerRef ?? $this->providerRef;
        $this->processedAt = $now;
        $this->failureReason = null;
    }

    public function fail(string $reason, DateTimeImmutable $now): void
    {
        $this->status = RefundStatus::FAILED;
        $this->failureReason = mb_substr($reason, 0, 255);
        $this->processedAt = $now;
    }
}
