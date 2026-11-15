<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

/** A seat on the plan. `id` is the performance seat (what holds and tickets point at). */
final readonly class SeatMapSeatView
{
    public function __construct(
        public string $id,
        public string $zoneId,
        public string $row,
        public int $number,
        public string $label,
        public float $x,
        public float $y,
        public bool $accessible,
    ) {
    }
}
