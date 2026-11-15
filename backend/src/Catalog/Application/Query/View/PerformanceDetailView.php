<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

final readonly class PerformanceDetailView
{
    public function __construct(
        public string $id,
        public string $eventId,
        public string $eventSlug,
        public string $eventTitle,
        public ?string $eventSubtitle,
        public string $category,
        public string $categoryLabel,
        public ?string $posterUrl,
        public int $durationMinutes,
        public ?int $ageRating,
        public string $startsAt,
        public string $doorsAt,
        public string $endsAt,
        public string $salesOpenAt,
        public string $salesCloseAt,
        public string $status,
        public bool $isOnSale,
        public ?string $cancelReason,
        public int $remaining,
        public int $capacity,
        public ?int $fromPriceCents,
        public string $currency,
        public string $layoutId,
        public string $layoutName,
    ) {
    }
}
