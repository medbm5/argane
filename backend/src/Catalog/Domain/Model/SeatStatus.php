<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

enum SeatStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case HELD = 'HELD';
    case SOLD = 'SOLD';
    case BLOCKED = 'BLOCKED';
}
