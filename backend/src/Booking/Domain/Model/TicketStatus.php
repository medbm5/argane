<?php

declare(strict_types=1);

namespace App\Booking\Domain\Model;

enum TicketStatus: string
{
    /** Order awaiting payment: not admissible and, unlike VALID, does not claim the seat. */
    case PENDING = 'PENDING';
    case VALID = 'VALID';
    case CHECKED_IN = 'CHECKED_IN';
    case CANCELLED = 'CANCELLED';
    case REFUNDED = 'REFUNDED';

    public function isLive(): bool
    {
        return self::VALID === $this || self::CHECKED_IN === $this;
    }
}
