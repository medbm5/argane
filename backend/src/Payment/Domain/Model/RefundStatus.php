<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

enum RefundStatus: string
{
    case PENDING = 'PENDING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
    /** Box-office sale after a performance cancellation: to be paid back at the counter. */
    case AWAITING_COUNTER = 'AWAITING_COUNTER';
}
