<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

final readonly class StandingAvailabilityView
{
    public function __construct(
        public string $zoneId,
        public int $remaining,
        public int $capacity,
    ) {
    }
}
