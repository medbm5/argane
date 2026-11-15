<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

/**
 * Static plan of a performance: geometry and the prices the caller may buy.
 * Changes rarely; availability is served separately and polled.
 */
final readonly class SeatMapView
{
    /**
     * @param array{width: int, height: int}                 $viewBox
     * @param array{x: int, y: int, width: int, height: int} $stage
     * @param list<SeatMapZoneView>                          $zones
     * @param list<SeatMapSeatView>                          $seats
     */
    public function __construct(
        public string $performanceId,
        public string $layoutId,
        public string $layoutName,
        public string $channel,
        public string $currency,
        public array $viewBox,
        public array $stage,
        public int $maxSeatsPerOrder,
        public array $zones,
        public array $seats,
    ) {
    }
}
