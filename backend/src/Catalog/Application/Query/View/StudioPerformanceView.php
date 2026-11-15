<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

final readonly class StudioPerformanceView
{
    public function __construct(
        public string $id,
        public string $startsAt,
        public string $doorsAt,
        public string $salesOpenAt,
        public string $salesCloseAt,
        /** SCHEDULED, ON_SALE, SALES_CLOSED, CANCELLED, PAST */
        public string $status,
        public bool $published,
        public ?string $cancelReason,
        public string $layoutId,
        public string $layoutName,
        public int $sold,
        public int $capacity,
        public int $revenueCents,
        public int $pricedCells,
    ) {
    }
}
