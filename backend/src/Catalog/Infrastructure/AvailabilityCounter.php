<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Counts places still buyable per performance, honouring lazy hold expiry:
 * a HELD seat whose hold has expired is available, and only ACTIVE, unexpired
 * standing holds consume standing stock. (Reads Booking's hold tables directly:
 * this is a reporting query, not a write path.)
 */
final readonly class AvailabilityCounter
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param list<string> $performanceIds binary UUIDs
     *
     * @return array<string, array{remaining: int, capacity: int}> keyed by binary id
     */
    public function count(array $performanceIds, DateTimeImmutable $now): array
    {
        if ([] === $performanceIds) {
            return [];
        }
        $at = $now->format('Y-m-d H:i:s');
        $result = [];

        $seats = $this->connection->fetchAllAssociative(
            "SELECT performance_id AS id,
                    SUM(status = 'AVAILABLE' OR (status = 'HELD' AND hold_expires_at <= :now)) AS remaining,
                    SUM(status <> 'BLOCKED') AS capacity
               FROM performance_seat
              WHERE performance_id IN (:ids)
           GROUP BY performance_id",
            ['ids' => $performanceIds, 'now' => $at],
            ['ids' => ArrayParameterType::BINARY],
        );
        foreach ($seats as $row) {
            $result[(string) $row['id']] = ['remaining' => (int) $row['remaining'], 'capacity' => (int) $row['capacity']];
        }

        $standing = $this->connection->fetchAllAssociative(
            "SELECT s.performance_id AS id,
                    SUM(GREATEST(0, s.capacity - s.sold_count - COALESCE(h.held, 0))) AS remaining,
                    SUM(s.capacity) AS capacity
               FROM performance_zone_stock s
          LEFT JOIN (SELECT h.performance_id, l.zone_id, SUM(l.quantity) AS held
                       FROM standing_hold_line l
                       JOIN hold h ON h.id = l.hold_id
                      WHERE h.status = 'ACTIVE' AND h.expires_at > :now
                   GROUP BY h.performance_id, l.zone_id) h
                 ON h.performance_id = s.performance_id AND h.zone_id = s.zone_id
              WHERE s.performance_id IN (:ids)
           GROUP BY s.performance_id",
            ['ids' => $performanceIds, 'now' => $at],
            ['ids' => ArrayParameterType::BINARY],
        );
        foreach ($standing as $row) {
            $id = (string) $row['id'];
            $result[$id] = [
                'remaining' => ($result[$id]['remaining'] ?? 0) + (int) $row['remaining'],
                'capacity' => ($result[$id]['capacity'] ?? 0) + (int) $row['capacity'],
            ];
        }

        return $result;
    }
}
