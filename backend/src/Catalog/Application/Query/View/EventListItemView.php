<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

final readonly class EventListItemView
{
    /**
     * @param list<string> $months venue months (YYYY-MM) of the listed upcoming dates, in order
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $subtitle,
        public string $category,
        public string $categoryLabel,
        public ?string $posterUrl,
        public int $durationMinutes,
        public ?int $ageRating,
        public int $performanceCount,
        public ?int $fromPriceCents,
        public string $currency,
        public ?PerformanceSummaryView $nextPerformance,
        public array $months,
    ) {
    }
}
