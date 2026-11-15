<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure;

use App\Catalog\Domain\InventoryRepository;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\Model\SeatStatus;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Bulk inserts inventory rows (≈ 400 per performance) with multi-row INSERT IGNORE,
 * relying on the unique (performance_id, seat_id) / (performance_id, zone_id) keys
 * to make re-runs harmless.
 */
final readonly class DbalInventoryRepository implements InventoryRepository
{
    private const int CHUNK = 250;

    public function __construct(private Connection $connection)
    {
    }

    public function generate(Performance $performance, DateTimeImmutable $now): array
    {
        $perfId = $performance->id()->toBinary();
        $stamp = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $seatRows = [];
        $stockRows = [];

        foreach ($performance->layout()->zones() as $zone) {
            if ($zone->isStanding()) {
                $stockRows[] = [Uuid::v7()->toBinary(), $perfId, $zone->id()->toBinary(), $zone->capacity() ?? 0, 0];
                continue;
            }
            foreach ($zone->seats() as $seat) {
                $status = $seat->isBlocked() ? SeatStatus::BLOCKED : SeatStatus::AVAILABLE;
                $seatRows[] = [Uuid::v7()->toBinary(), $perfId, $seat->id()->toBinary(), $zone->id()->toBinary(), $status->value, $stamp];
            }
        }

        $seats = 0;
        foreach (array_chunk($seatRows, self::CHUNK) as $chunk) {
            $seats += $this->insertIgnore('performance_seat', ['id', 'performance_id', 'seat_id', 'zone_id', 'status', 'updated_at'], $chunk);
        }
        $standing = 0;
        if ([] !== $stockRows) {
            $standing = $this->insertIgnore('performance_zone_stock', ['id', 'performance_id', 'zone_id', 'capacity', 'sold_count'], $stockRows);
        }

        return ['seats' => $seats, 'standing' => $standing];
    }

    public function count(Performance $performance): array
    {
        $id = $performance->id()->toBinary();

        return [
            'seats' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM performance_seat WHERE performance_id = ?', [$id]),
            'standingZones' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM performance_zone_stock WHERE performance_id = ?', [$id]),
        ];
    }

    /**
     * @param list<string>           $columns
     * @param list<list<string|int>> $rows
     */
    private function insertIgnore(string $table, array $columns, array $rows): int
    {
        $placeholders = '('.implode(', ', array_fill(0, \count($columns), '?')).')';
        $sql = sprintf(
            'INSERT IGNORE INTO %s (%s) VALUES %s',
            $table,
            implode(', ', $columns),
            implode(', ', array_fill(0, \count($rows), $placeholders)),
        );

        return (int) $this->connection->executeStatement($sql, array_merge(...$rows));
    }
}
