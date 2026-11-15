<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Model\Performance;
use DateTimeImmutable;

interface InventoryRepository
{
    /**
     * Creates one performance_seat row per seat (blocked seats as BLOCKED) and one
     * performance_zone_stock row per standing zone. Idempotent: existing rows are kept.
     *
     * @return array{seats: int, standing: int} rows created
     */
    public function generate(Performance $performance, DateTimeImmutable $now): array;

    /**
     * @return array{seats: int, standingZones: int}
     */
    public function count(Performance $performance): array;
}
