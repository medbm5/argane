<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure;

use App\Catalog\Domain\InventoryRepository;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\Model\SeatStatus;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
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

    public function holdSeats(Uuid $performanceId, array $seatIds, Uuid $holdId, DateTimeImmutable $expiresAt, DateTimeImmutable $now): array
    {
        if ([] === $seatIds) {
            return [];
        }
        $binaryIds = array_map(static fn (Uuid $id): string => $id->toBinary(), $seatIds);
        $this->connection->executeStatement(
            "UPDATE performance_seat
                SET status = 'HELD', hold_id = :hold, hold_expires_at = :expires, updated_at = :now
              WHERE performance_id = :p
                AND id IN (:ids)
                AND (status = 'AVAILABLE' OR (status = 'HELD' AND hold_expires_at <= :now))",
            ['hold' => $holdId->toBinary(), 'expires' => self::sql($expiresAt), 'now' => self::sql($now), 'p' => $performanceId->toBinary(), 'ids' => $binaryIds],
            ['hold' => ParameterType::BINARY, 'p' => ParameterType::BINARY, 'ids' => ArrayParameterType::BINARY],
        );

        // Current read of our own writes: which seats really carry this hold now.
        $won = $this->connection->fetchFirstColumn(
            'SELECT id FROM performance_seat WHERE hold_id = :hold AND id IN (:ids)',
            ['hold' => $holdId->toBinary(), 'ids' => $binaryIds],
            ['hold' => ParameterType::BINARY, 'ids' => ArrayParameterType::BINARY],
        );
        $wonSet = array_flip(array_map(static fn (mixed $id): string => (string) $id, $won));

        return array_values(array_map(
            static fn (Uuid $id): string => $id->toRfc4122(),
            array_filter($seatIds, static fn (Uuid $id): bool => !isset($wonSet[$id->toBinary()])),
        ));
    }

    public function seatsOfHold(Uuid $holdId): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM performance_seat WHERE hold_id = ? ORDER BY id',
            [$holdId->toBinary()],
            [ParameterType::BINARY],
        );

        return array_map(static fn (mixed $id): string => Uuid::fromBinary((string) $id)->toRfc4122(), $ids);
    }

    public function releaseSeats(Uuid $holdId, DateTimeImmutable $now): int
    {
        return (int) $this->connection->executeStatement(
            "UPDATE performance_seat SET status = 'AVAILABLE', hold_id = NULL, hold_expires_at = NULL, updated_at = :now
              WHERE hold_id = :hold AND status = 'HELD'",
            ['hold' => $holdId->toBinary(), 'now' => self::sql($now)],
            ['hold' => ParameterType::BINARY],
        );
    }

    public function sellSeat(Uuid $performanceSeatId, Uuid $ticketId, ?Uuid $holdId, DateTimeImmutable $now): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            "UPDATE performance_seat
                SET status = 'SOLD', ticket_id = :ticket, hold_id = NULL, hold_expires_at = NULL, updated_at = :now
              WHERE id = :seat
                AND (status = 'AVAILABLE'
                     OR (status = 'HELD' AND (hold_id = :hold OR hold_expires_at <= :now)))",
            ['ticket' => $ticketId->toBinary(), 'now' => self::sql($now), 'seat' => $performanceSeatId->toBinary(), 'hold' => ($holdId ?? Uuid::fromString('00000000-0000-0000-0000-000000000000'))->toBinary()],
            ['ticket' => ParameterType::BINARY, 'seat' => ParameterType::BINARY, 'hold' => ParameterType::BINARY],
        );
    }

    public function sellStanding(Uuid $performanceId, Uuid $zoneId, int $quantity, ?Uuid $holdId, DateTimeImmutable $now): bool
    {
        $stock = $this->lockStanding($performanceId, $zoneId, $now, $holdId);
        if (null === $stock || $stock['remaining'] < $quantity) {
            return false;
        }
        $this->connection->executeStatement(
            'UPDATE performance_zone_stock SET sold_count = sold_count + :q WHERE performance_id = :p AND zone_id = :z',
            ['q' => $quantity, 'p' => $performanceId->toBinary(), 'z' => $zoneId->toBinary()],
            ['p' => ParameterType::BINARY, 'z' => ParameterType::BINARY],
        );

        return true;
    }

    public function unsellSeat(Uuid $performanceSeatId, Uuid $ticketId, DateTimeImmutable $now): void
    {
        $this->connection->executeStatement(
            "UPDATE performance_seat SET status = 'AVAILABLE', ticket_id = NULL, updated_at = :now
              WHERE id = :seat AND ticket_id = :ticket AND status = 'SOLD'",
            ['now' => self::sql($now), 'seat' => $performanceSeatId->toBinary(), 'ticket' => $ticketId->toBinary()],
            ['seat' => ParameterType::BINARY, 'ticket' => ParameterType::BINARY],
        );
    }

    public function unsellStanding(Uuid $performanceId, Uuid $zoneId, int $quantity): void
    {
        $this->connection->executeStatement(
            'UPDATE performance_zone_stock SET sold_count = GREATEST(0, sold_count - :q) WHERE performance_id = :p AND zone_id = :z',
            ['q' => $quantity, 'p' => $performanceId->toBinary(), 'z' => $zoneId->toBinary()],
            ['p' => ParameterType::BINARY, 'z' => ParameterType::BINARY],
        );
    }

    public function releaseExpiredSeats(DateTimeImmutable $now): int
    {
        return (int) $this->connection->executeStatement(
            "UPDATE performance_seat SET status = 'AVAILABLE', hold_id = NULL, hold_expires_at = NULL, updated_at = :now
              WHERE status = 'HELD' AND hold_expires_at <= :now",
            ['now' => self::sql($now)],
        );
    }

    public function lockStanding(Uuid $performanceId, Uuid $zoneId, DateTimeImmutable $now, ?Uuid $exceptHoldId = null): ?array
    {
        $stock = $this->connection->fetchAssociative(
            'SELECT capacity, sold_count FROM performance_zone_stock WHERE performance_id = :p AND zone_id = :z FOR UPDATE',
            ['p' => $performanceId->toBinary(), 'z' => $zoneId->toBinary()],
            ['p' => ParameterType::BINARY, 'z' => ParameterType::BINARY],
        );
        if (false === $stock) {
            return null;
        }

        // A locking read sees the latest committed holds; a plain read would use the
        // transaction snapshot and could oversell under REPEATABLE READ.
        $held = (int) $this->connection->fetchOne(
            "SELECT COALESCE(SUM(l.quantity), 0)
               FROM standing_hold_line l
               JOIN hold h ON h.id = l.hold_id
              WHERE h.performance_id = :p AND l.zone_id = :z
                AND h.status = 'ACTIVE' AND h.expires_at > :now
                AND h.id <> :except
                FOR SHARE",
            ['p' => $performanceId->toBinary(), 'z' => $zoneId->toBinary(), 'now' => self::sql($now), 'except' => ($exceptHoldId ?? Uuid::fromString('00000000-0000-0000-0000-000000000000'))->toBinary()],
            ['p' => ParameterType::BINARY, 'z' => ParameterType::BINARY, 'except' => ParameterType::BINARY],
        );

        $capacity = (int) $stock['capacity'];

        return ['capacity' => $capacity, 'remaining' => max(0, $capacity - (int) $stock['sold_count'] - $held)];
    }

    private static function sql(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
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
