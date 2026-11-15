<?php

declare(strict_types=1);

namespace App\Venue\Domain;

use App\Venue\Domain\Model\HallLayout;
use App\Venue\Domain\Model\Venue;
use Symfony\Component\Uid\Uuid;

interface VenueRepository
{
    public function save(Venue $venue): void;

    public function findBySlug(string $slug): ?Venue;

    /** The single venue of this deployment (schema is multi-venue ready). */
    public function main(): ?Venue;

    public function saveLayout(HallLayout $layout): void;

    public function findLayout(Uuid $id): ?HallLayout;

    /**
     * @return list<HallLayout>
     */
    public function layoutsOf(Venue $venue, bool $includeArchived = false): array;
}
