<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\Query\View\SeatMapSeatView;
use App\Catalog\Application\Query\View\StandingAvailabilityView;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface SeatMapQueries
{
    /**
     * @return list<SeatMapSeatView> in zone order, then row, then number
     */
    public function seats(Uuid $performanceId): array;

    /**
     * Seat ids grouped by effective state, honouring lazy hold expiry.
     *
     * @return array{held: list<string>, mine: list<string>, sold: list<string>, blocked: list<string>, free: int}
     */
    public function seatStates(Uuid $performanceId, DateTimeImmutable $now, ?Uuid $userId): array;

    /**
     * @return list<StandingAvailabilityView>
     */
    public function standing(Uuid $performanceId, DateTimeImmutable $now): array;
}
