<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure;

use App\Catalog\Application\Query\SeatMapQueries;
use App\Catalog\Application\Query\View\SeatMapSeatView;
use App\Catalog\Application\Query\View\StandingAvailabilityView;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Seat map reads in plain SQL: a hall has hundreds of seats and the availability
 * endpoint is polled, so no entity hydration here.
 */
final readonly class DbalSeatMapQueries implements SeatMapQueries
{
    public function __construct(private Connection $connection)
    {
    }

    public function seats(Uuid $performanceId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT ps.id, ps.zone_id, s.row_label, s.number, s.x, s.y, s.is_accessible
               FROM performance_seat ps
               JOIN seat s ON s.id = ps.seat_id
               JOIN zone z ON z.id = ps.zone_id
              WHERE ps.performance_id = :p
           ORDER BY z.sort_order, s.y, s.x',
            ['p' => $performanceId->toBinary()],
            ['p' => ParameterType::BINARY],
        );

        return array_map(static fn (array $r): SeatMapSeatView => new SeatMapSeatView(
            Uuid::fromBinary((string) $r['id'])->toRfc4122(),
            Uuid::fromBinary((string) $r['zone_id'])->toRfc4122(),
            (string) $r['row_label'],
            (int) $r['number'],
            $r['row_label'].'-'.$r['number'],
            round((float) $r['x'], 2),
            round((float) $r['y'], 2),
            (bool) $r['is_accessible'],
        ), $rows);
    }

    public function seatStates(Uuid $performanceId, DateTimeImmutable $now, ?Uuid $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT ps.id,
                    CASE
                      WHEN ps.status = 'HELD' AND ps.hold_expires_at > :now
                        THEN IF(h.id IS NOT NULL AND h.user_id = :u AND h.status = 'ACTIVE', 'MINE', 'HELD')
                      WHEN ps.status = 'HELD' THEN 'AVAILABLE'
                      ELSE ps.status
                    END AS state
               FROM performance_seat ps
          LEFT JOIN hold h ON h.id = ps.hold_id
              WHERE ps.performance_id = :p
           ORDER BY ps.id",
            ['p' => $performanceId->toBinary(), 'now' => $now->format('Y-m-d H:i:s'), 'u' => $userId?->toBinary()],
            ['p' => ParameterType::BINARY, 'u' => null === $userId ? ParameterType::NULL : ParameterType::BINARY],
        );

        $states = ['held' => [], 'mine' => [], 'sold' => [], 'blocked' => [], 'free' => 0];
        foreach ($rows as $r) {
            $id = Uuid::fromBinary((string) $r['id'])->toRfc4122();
            match ($r['state']) {
                'HELD' => $states['held'][] = $id,
                'MINE' => $states['mine'][] = $id,
                'SOLD' => $states['sold'][] = $id,
                'BLOCKED' => $states['blocked'][] = $id,
                default => ++$states['free'],
            };
        }

        return $states;
    }

    public function standing(Uuid $performanceId, DateTimeImmutable $now): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT s.zone_id, s.capacity, GREATEST(0, s.capacity - s.sold_count - COALESCE(h.held, 0)) AS remaining
               FROM performance_zone_stock s
          LEFT JOIN (SELECT l.zone_id, SUM(l.quantity) AS held
                       FROM standing_hold_line l
                       JOIN hold h ON h.id = l.hold_id
                      WHERE h.performance_id = :p AND h.status = 'ACTIVE' AND h.expires_at > :now
                   GROUP BY l.zone_id) h ON h.zone_id = s.zone_id
              WHERE s.performance_id = :p
           ORDER BY s.zone_id",
            ['p' => $performanceId->toBinary(), 'now' => $now->format('Y-m-d H:i:s')],
            ['p' => ParameterType::BINARY],
        );

        return array_map(static fn (array $r): StandingAvailabilityView => new StandingAvailabilityView(
            Uuid::fromBinary((string) $r['zone_id'])->toRfc4122(),
            (int) $r['remaining'],
            (int) $r['capacity'],
        ), $rows);
    }
}
