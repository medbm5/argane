<?php

declare(strict_types=1);

namespace App\BoxOffice\Application\Dto;

/** A performance as the counter needs it: when, what, how full, can I sell. */
final readonly class CounterPerformanceView
{
    public function __construct(
        public string $id,
        public string $eventTitle,
        public string $eventSlug,
        public string $category,
        public ?string $posterUrl,
        public string $startsAt,
        public string $status,
        public bool $sellable,
        public bool $onlineOpen,
        public int $remaining,
        public int $capacity,
        public string $layoutName,
    ) {
    }
}
