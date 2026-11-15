<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

final readonly class PerformanceSummaryView
{
    public function __construct(
        public string $id,
        /** ISO 8601, UTC */
        public string $startsAt,
        public string $doorsAt,
        /** SCHEDULED | ON_SALE | SALES_CLOSED | CANCELLED | PAST | SOLD_OUT */
        public string $status,
        public bool $isOnSale,
        public int $remaining,
        public int $capacity,
        public ?int $fromPriceCents,
        public string $layoutName,
    ) {
    }
}
