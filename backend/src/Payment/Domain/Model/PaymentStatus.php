<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}
