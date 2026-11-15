<?php

declare(strict_types=1);

namespace App\Booking\Domain\Model;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';
    case REFUNDED = 'REFUNDED';
    case PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';
    case EXPIRED = 'EXPIRED';
    case FAILED = 'FAILED';

    /**
     * Allowed transitions. EXPIRED/FAILED → PAID covers a late Stripe success
     * whose seats could be re-acquired.
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::PENDING_PAYMENT => [self::PAID, self::EXPIRED, self::FAILED, self::CANCELLED],
            self::FAILED => [self::PENDING_PAYMENT, self::PAID, self::EXPIRED, self::CANCELLED],
            self::EXPIRED => [self::PAID],
            self::PAID => [self::CANCELLED, self::REFUNDED, self::PARTIALLY_REFUNDED],
            self::PARTIALLY_REFUNDED => [self::REFUNDED, self::CANCELLED],
            self::CANCELLED => [self::REFUNDED],
            self::REFUNDED => [],
        };
    }

    public function canBecome(self $target): bool
    {
        return \in_array($target, $this->next(), true);
    }

    /** Tickets of these orders are live. */
    public function isSettled(): bool
    {
        return self::PAID === $this || self::PARTIALLY_REFUNDED === $this;
    }
}
