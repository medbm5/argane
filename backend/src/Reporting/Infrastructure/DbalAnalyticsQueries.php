<?php

declare(strict_types=1);

namespace App\Reporting\Infrastructure;

use App\Reporting\Application\Query\AnalyticsQueries;
use App\Shared\Domain\VenueTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Plain SQL over the sales tables (indexes: see the analytics migration). Day buckets
 * shift UTC timestamps by the venue offset of the range start, so a day is a venue day.
 */
final readonly class DbalAnalyticsQueries implements AnalyticsQueries
{
    private const string LIVE = "('VALID', 'CHECKED_IN')";
    private const int CURVE_DAYS = 60;

    public function __construct(private Connection $connection)
    {
    }

    public function totals(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $range = ['from' => self::sql($from), 'to' => self::sql($to)];
        $sales = $this->connection->fetchAssociative(
            'SELECT COALESCE(SUM(t.unit_price_cents), 0) AS revenue, COUNT(t.id) AS tickets,
                    COUNT(DISTINCT o.id) AS orders, COALESCE(SUM(o.channel = \'ONLINE\'), 0) AS online
               FROM customer_order o JOIN ticket t ON t.order_id = o.id AND t.status IN '.self::LIVE.'
              WHERE o.paid_at >= :from AND o.paid_at < :to',
            $range,
        ) ?: [];
        $refunded = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM customer_order WHERE status IN ('REFUNDED', 'CANCELLED') AND paid_at IS NOT NULL AND cancelled_at >= :from AND cancelled_at < :to",
            $range,
        );
        $fill = array_reduce($this->fill($from, $to), static fn (array $c, array $p): array => [$c[0] + $p['sold'], $c[1] + $p['capacity']], [0, 0]);

        return [
            'revenueCents' => (int) ($sales['revenue'] ?? 0),
            'tickets' => (int) ($sales['tickets'] ?? 0),
            'orders' => (int) ($sales['orders'] ?? 0),
            'onlineTickets' => (int) ($sales['online'] ?? 0),
            'refundedOrders' => $refunded,
            'sold' => $fill[0],
            'capacity' => $fill[1],
        ];
    }

    public function daily(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $offset = VenueTime::toVenue($from)->getOffset();
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DATE(DATE_ADD(o.paid_at, INTERVAL :offset SECOND)) AS day, COUNT(t.id) AS tickets, COALESCE(SUM(t.unit_price_cents), 0) AS revenue
               FROM customer_order o JOIN ticket t ON t.order_id = o.id AND t.status IN '.self::LIVE.'
              WHERE o.paid_at >= :from AND o.paid_at < :to
           GROUP BY day',
            ['offset' => $offset, 'from' => self::sql($from), 'to' => self::sql($to)],
            ['offset' => ParameterType::INTEGER],
        );
        $byDay = array_column($rows, null, 'day');

        $series = [];
        for ($day = VenueTime::toVenue($from)->setTime(0, 0); $day < VenueTime::toVenue($to); $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $series[] = ['date' => $key, 'tickets' => (int) ($byDay[$key]['tickets'] ?? 0), 'revenueCents' => (int) ($byDay[$key]['revenue'] ?? 0)];
        }

        return $series;
    }

    public function fill(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $performances = $this->connection->fetchAllAssociative(
            'SELECT p.id, p.starts_at, p.status, p.cancelled_at, e.title
               FROM performance p JOIN event e ON e.id = p.event_id
              WHERE p.published_at IS NOT NULL AND p.cancelled_at IS NULL AND p.starts_at >= :from AND p.starts_at < :to
           ORDER BY p.starts_at',
            ['from' => self::sql($from), 'to' => self::sql($to)],
        );
        if ([] === $performances) {
            return [];
        }
        $ids = array_column($performances, 'id');
        $seated = $this->connection->fetchAllAssociative(
            "SELECT ps.performance_id, z.code, z.name, z.sort_order, SUM(ps.status <> 'BLOCKED') AS capacity, SUM(ps.status = 'SOLD') AS sold
               FROM performance_seat ps JOIN zone z ON z.id = ps.zone_id
              WHERE ps.performance_id IN (:ids) GROUP BY ps.performance_id, z.id",
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY],
        );
        $standing = $this->connection->fetchAllAssociative(
            'SELECT s.performance_id, z.code, z.name, z.sort_order, s.capacity, s.sold_count AS sold
               FROM performance_zone_stock s JOIN zone z ON z.id = s.zone_id WHERE s.performance_id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY],
        );
        $zones = [];
        foreach ([...$seated, ...$standing] as $z) {
            $zones[(string) $z['performance_id']][] = ['code' => (string) $z['code'], 'name' => (string) $z['name'], 'order' => (int) $z['sort_order'], 'sold' => (int) $z['sold'], 'capacity' => (int) $z['capacity']];
        }

        return array_map(static function (array $p) use ($zones): array {
            $list = $zones[(string) $p['id']] ?? [];
            usort($list, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
            $list = array_map(static fn (array $z): array => ['code' => $z['code'], 'name' => $z['name'], 'sold' => $z['sold'], 'capacity' => $z['capacity']], $list);

            return [
                'performanceId' => Uuid::fromBinary((string) $p['id'])->toRfc4122(),
                'eventTitle' => (string) $p['title'],
                'startsAt' => self::atom($p['starts_at']),
                'status' => (string) $p['status'],
                'sold' => array_sum(array_column($list, 'sold')),
                'capacity' => array_sum(array_column($list, 'capacity')),
                'zones' => $list,
            ];
        }, $performances);
    }

    public function revenueBreakdown(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $range = ['from' => self::sql($from), 'to' => self::sql($to)];
        $base = 'FROM customer_order o JOIN ticket t ON t.order_id = o.id AND t.status IN '.self::LIVE.' WHERE o.paid_at >= :from AND o.paid_at < :to';
        $byTariff = $this->connection->fetchAllAssociative("SELECT t.tariff_label AS label, COUNT(*) AS tickets, SUM(t.unit_price_cents) AS revenue $base GROUP BY t.tariff_label ORDER BY revenue DESC, tickets DESC", $range);
        $byChannel = $this->connection->fetchAllAssociative("SELECT o.channel AS label, COUNT(*) AS tickets, SUM(t.unit_price_cents) AS revenue $base GROUP BY o.channel ORDER BY revenue DESC", $range);

        return [
            'byTariff' => array_map(static fn (array $r): array => ['tariff' => (string) $r['label'], 'tickets' => (int) $r['tickets'], 'revenueCents' => (int) $r['revenue']], $byTariff),
            'byChannel' => array_map(static fn (array $r): array => ['channel' => (string) $r['label'], 'tickets' => (int) $r['tickets'], 'revenueCents' => (int) $r['revenue']], $byChannel),
        ];
    }

    public function bookingCurve(DateTimeImmutable $now, ?string $performanceId): array
    {
        // Reference: past, sold performances. Tickets per days-before, cumulated, over capacity.
        $past = $this->connection->fetchAllAssociative(
            'SELECT p.id FROM performance p WHERE p.published_at IS NOT NULL AND p.cancelled_at IS NULL AND p.starts_at < :now',
            ['now' => self::sql($now)],
        );
        $reference = $this->curve(array_column($past, 'id'));
        $own = null !== $performanceId && Uuid::isValid($performanceId) ? $this->curve([Uuid::fromString($performanceId)->toBinary()]) : [];

        return ['reference' => $reference, 'performance' => $own];
    }

    public function seatHeatmap(string $layoutId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        if (!Uuid::isValid($layoutId)) {
            return ['layoutId' => $layoutId, 'performances' => 0, 'seats' => []];
        }
        $layout = Uuid::fromString($layoutId)->toBinary();
        $performances = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM performance WHERE layout_id = :l AND published_at IS NOT NULL AND cancelled_at IS NULL AND starts_at >= :from AND starts_at < :to',
            ['l' => $layout, 'from' => self::sql($from), 'to' => self::sql($to)],
            ['l' => ParameterType::BINARY],
        );
        $rows = $this->connection->fetchAllAssociative(
            "SELECT s.row_label, s.number, s.x, s.y, z.code, COALESCE(SUM(ps.status = 'SOLD'), 0) AS sold
               FROM seat s JOIN zone z ON z.id = s.zone_id
          LEFT JOIN performance_seat ps ON ps.seat_id = s.id
          LEFT JOIN performance p ON p.id = ps.performance_id
               AND p.published_at IS NOT NULL AND p.cancelled_at IS NULL AND p.starts_at >= :from AND p.starts_at < :to
              WHERE z.layout_id = :l AND s.is_blocked = 0 AND (ps.id IS NULL OR p.id IS NOT NULL)
           GROUP BY s.id",
            ['l' => $layout, 'from' => self::sql($from), 'to' => self::sql($to)],
            ['l' => ParameterType::BINARY],
        );

        return [
            'layoutId' => $layoutId,
            'performances' => $performances,
            'seats' => array_map(static fn (array $r): array => [
                'label' => $r['row_label'].'-'.$r['number'],
                'zone' => (string) $r['code'],
                'x' => round((float) $r['x'], 2),
                'y' => round((float) $r['y'], 2),
                'rate' => $performances > 0 ? round(min(1, (int) $r['sold'] / $performances), 3) : 0.0,
            ], $rows),
        ];
    }

    public function atRisk(DateTimeImmutable $now): array
    {
        $reference = array_column($this->bookingCurve($now, null)['reference'], 'rate', 'daysBefore');
        $risky = [];
        foreach ($this->fill($now, $now->modify('+45 days')) as $p) {
            if ('ON_SALE' !== $p['status'] || 0 === $p['capacity']) {
                continue;
            }
            $daysLeft = max(0, (int) floor(((new DateTimeImmutable($p['startsAt']))->getTimestamp() - $now->getTimestamp()) / 86400));
            $expected = (float) ($reference[min(self::CURVE_DAYS, $daysLeft)] ?? 0.0);
            $rate = round($p['sold'] / $p['capacity'], 3);
            if ($expected > 0 && $rate < 0.7 * $expected || $daysLeft <= 7 && $rate < 0.25) {
                $risky[] = [
                    'performanceId' => $p['performanceId'],
                    'eventTitle' => $p['eventTitle'],
                    'startsAt' => $p['startsAt'],
                    'daysLeft' => $daysLeft,
                    'rate' => $rate,
                    'expected' => round($expected, 3),
                    'sold' => $p['sold'],
                    'capacity' => $p['capacity'],
                ];
            }
        }
        usort($risky, static fn (array $a, array $b): int => $a['daysLeft'] <=> $b['daysLeft']);

        return $risky;
    }

    /**
     * Average cumulative share of capacity sold by N days before the show, over the
     * given performances (each performance weighs the same).
     *
     * @param list<mixed> $performanceIds binary ids
     *
     * @return list<array{daysBefore: int, rate: float}>
     */
    private function curve(array $performanceIds): array
    {
        if ([] === $performanceIds) {
            return [];
        }
        $rates = array_fill(0, self::CURVE_DAYS + 1, 0.0);
        $count = 0;
        foreach ($performanceIds as $id) {
            $capacity = (int) $this->connection->fetchOne(
                "SELECT (SELECT COUNT(*) FROM performance_seat WHERE performance_id = :p AND status <> 'BLOCKED')
                      + (SELECT COALESCE(SUM(capacity), 0) FROM performance_zone_stock WHERE performance_id = :p)",
                ['p' => $id],
                ['p' => ParameterType::BINARY],
            );
            if (0 === $capacity) {
                continue;
            }
            ++$count;
            $rows = $this->connection->fetchAllAssociative(
                'SELECT LEAST(:max, GREATEST(0, DATEDIFF(p.starts_at, o.paid_at))) AS d, COUNT(*) AS n
                   FROM ticket t JOIN customer_order o ON o.id = t.order_id JOIN performance p ON p.id = t.performance_id
                  WHERE t.performance_id = :p AND t.status IN '.self::LIVE.' AND o.paid_at IS NOT NULL
               GROUP BY d',
                ['p' => $id, 'max' => self::CURVE_DAYS],
                ['p' => ParameterType::BINARY, 'max' => ParameterType::INTEGER],
            );
            $perDay = array_column($rows, 'n', 'd');
            $cumulative = 0;
            for ($d = self::CURVE_DAYS; $d >= 0; --$d) {
                $cumulative += (int) ($perDay[$d] ?? 0);
                $rates[$d] += $cumulative / $capacity;
            }
        }
        if (0 === $count) {
            return [];
        }

        $points = [];
        for ($d = self::CURVE_DAYS; $d >= 0; --$d) {
            $points[] = ['daysBefore' => $d, 'rate' => round($rates[$d] / $count, 4)];
        }

        return $points;
    }

    private static function sql(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function atom(mixed $value): string
    {
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))->format(\DATE_ATOM);
    }
}
