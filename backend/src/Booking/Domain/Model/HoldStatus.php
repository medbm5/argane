<?php

declare(strict_types=1);

namespace App\Booking\Domain\Model;

enum HoldStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CONVERTED = 'CONVERTED';
    case RELEASED = 'RELEASED';
    case EXPIRED = 'EXPIRED';
}
