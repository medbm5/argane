<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

final readonly class SeatMapZoneView
{
    /**
     * @param list<ZonePriceView> $prices cheapest first; empty when nothing is buyable here for the caller
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $kind,
        public string $colorToken,
        public int $sortOrder,
        /** SVG path in plan coordinates */
        public string $shape,
        /** Standing zones only */
        public ?int $capacity,
        public array $prices,
    ) {
    }
}
