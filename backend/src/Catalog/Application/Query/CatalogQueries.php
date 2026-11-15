<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\Query\View\EventDetailView;
use App\Catalog\Application\Query\View\EventListItemView;
use App\Catalog\Application\Query\View\PerformanceDetailView;
use App\Catalog\Domain\Model\EventCategory;
use DateTimeImmutable;

/**
 * Public, read-only catalog. Only published events and published performances are visible.
 */
interface CatalogQueries
{
    /**
     * Events with at least one upcoming published performance (in $month when given, "YYYY-MM"
     * in the venue's time zone), ordered by their next performance.
     *
     * @return list<EventListItemView>
     */
    public function listEvents(DateTimeImmutable $now, ?EventCategory $category = null, ?string $month = null): array;

    public function eventBySlug(string $slug, DateTimeImmutable $now): ?EventDetailView;

    public function performance(string $performanceId, DateTimeImmutable $now): ?PerformanceDetailView;

    /**
     * Published performances starting in [from, to), soonest first (box office day list).
     *
     * @return list<PerformanceDetailView>
     */
    public function performancesBetween(DateTimeImmutable $from, DateTimeImmutable $to, DateTimeImmutable $now): array;
}
