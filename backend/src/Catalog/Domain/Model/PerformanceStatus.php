<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

enum PerformanceStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case ON_SALE = 'ON_SALE';
    case SALES_CLOSED = 'SALES_CLOSED';
    case CANCELLED = 'CANCELLED';
}
