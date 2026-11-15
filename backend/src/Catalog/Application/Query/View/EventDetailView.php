<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

final readonly class EventDetailView
{
    /**
     * @param list<PerformanceSummaryView> $performances upcoming first, then past
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $subtitle,
        public string $category,
        public string $categoryLabel,
        public string $description,
        public ?string $posterUrl,
        public int $durationMinutes,
        public ?int $ageRating,
        public ?int $fromPriceCents,
        public string $currency,
        public array $performances,
    ) {
    }
}
