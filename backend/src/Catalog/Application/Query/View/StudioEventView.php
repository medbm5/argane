<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

/** An event as the organiser sees it: every status, every performance. */
final readonly class StudioEventView
{
    /**
     * @param list<StudioPerformanceView> $performances
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $subtitle,
        public string $category,
        public string $categoryLabel,
        public string $description,
        public int $durationMinutes,
        public ?int $ageRating,
        public ?string $posterPath,
        public string $status,
        public int $sold,
        public int $capacity,
        public int $revenueCents,
        public array $performances,
    ) {
    }
}
