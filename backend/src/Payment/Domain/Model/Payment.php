<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

use App\Booking\Domain\Model\Order;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'payment')]
#[ORM\UniqueConstraint(name: 'uniq_payment_provider_ref', columns: ['provider', 'provider_ref'])]
#[ORM\Index(name: 'idx_payment_order', columns: ['order_id'])]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 16, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $settledAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Order $order,
        #[ORM\Column(length: 16, enumType: PaymentProvider::class)]
        private PaymentProvider $provider,
        #[ORM\Column]
        private int $amountCents,
        #[ORM\Column(length: 3)]
        private string $currency,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $createdAt,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $providerRef = null,
    ) {
        $this->id = Uuid::v7();
        $this->currency = strtoupper($currency);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function order(): Order
    {
        return $this->order;
    }

    public function provider(): PaymentProvider
    {
        return $this->provider;
    }

    public function providerRef(): ?string
    {
        return $this->providerRef;
    }

    public function attachProviderRef(string $ref): void
    {
        $this->providerRef = $ref;
    }

    public function amountCents(): int
    {
        return $this->amountCents;
    }

    public function changeAmount(int $amountCents): void
    {
        $this->amountCents = $amountCents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function settledAt(): ?DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function succeed(DateTimeImmutable $now): void
    {
        $this->status = PaymentStatus::SUCCEEDED;
        $this->settledAt ??= $now;
    }

    public function fail(DateTimeImmutable $now): void
    {
        if (PaymentStatus::SUCCEEDED !== $this->status) {
            $this->status = PaymentStatus::FAILED;
            $this->settledAt = $now;
        }
    }

    public function cancel(DateTimeImmutable $now): void
    {
        if (PaymentStatus::PENDING === $this->status || PaymentStatus::FAILED === $this->status) {
            $this->status = PaymentStatus::CANCELLED;
            $this->settledAt = $now;
        }
    }
}
