<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

/**
 * Compact availability: only seats that are NOT available are listed (sorted, so the
 * ETag is stable). Anything absent from these lists is free to take.
 */
final readonly class AvailabilityView
{
    /**
     * @param list<string>                   $held     held by someone else
     * @param list<string>                   $mine     held by the caller's active hold
     * @param list<string>                   $sold
     * @param list<string>                   $blocked
     * @param list<StandingAvailabilityView> $standing
     */
    public function __construct(
        public string $performanceId,
        public string $status,
        public bool $isOnSale,
        public int $remaining,
        public array $held,
        public array $mine,
        public array $sold,
        public array $blocked,
        public array $standing,
    ) {
    }
}
