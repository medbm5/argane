<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\Query\View\StudioEventView;
use DateTimeImmutable;

interface StudioCatalogQueries
{
    /**
     * Every event (drafts and archives included), newest first, with sales totals.
     *
     * @return list<StudioEventView>
     */
    public function events(DateTimeImmutable $now): array;

    public function event(string $eventId, DateTimeImmutable $now): ?StudioEventView;
}
