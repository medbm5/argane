<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure;

use App\Catalog\Application\Query\StudioCatalogQueries;
use App\Catalog\Application\Query\View\StudioEventView;
use App\Catalog\Application\Query\View\StudioPerformanceView;
use App\Catalog\Domain\Model\EventCategory;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Organiser lists: every event and performance with sold places, capacity, revenue and
 * how much of the price grid is filled. A handful of grouped queries, no N+1.
 */
final readonly class DbalStudioCatalogQueries implements StudioCatalogQueries
{
    public function __construct(private Connection $connection)
    {
    }

    public function events(DateTimeImmutable $now): array
    {
        return $this->build($this->connection->fetchAllAssociative('SELECT * FROM event ORDER BY created_at DESC'), $now);
    }

    public function event(string $eventId, DateTimeImmutable $now): ?StudioEventView
    {
        if (!Uuid::isValid($eventId)) {
            return null;
        }
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM event WHERE id = ?', [Uuid::fromString($eventId)->toBinary()]);

        return $this->build($rows, $now)[0] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $events
     *
     * @return list<StudioEventView>
     */
    private function build(array $events, DateTimeImmutable $now): array
    {
        if ([] === $events) {
            return [];
        }
        $performances = $this->connection->fetchAllAssociative(
            'SELECT p.*, l.name AS layout_name FROM performance p JOIN hall_layout l ON l.id = p.layout_id
              WHERE p.event_id IN (:ids) ORDER BY p.starts_at',
            ['ids' => array_column($events, 'id')],
            ['ids' => ArrayParameterType::BINARY],
        );
        $performanceIds = array_column($performances, 'id');
        $sales = $this->keyed(
            'SELECT performance_id AS k, COUNT(*) AS sold, COALESCE(SUM(unit_price_cents), 0) AS revenue
               FROM ticket WHERE status IN (\'VALID\', \'CHECKED_IN\') AND performance_id IN (:ids) GROUP BY performance_id',
            $performanceIds,
        );
        $priced = $this->keyed('SELECT performance_id AS k, COUNT(*) AS cells FROM performance_price WHERE performance_id IN (:ids) GROUP BY performance_id', $performanceIds);
        $capacity = $this->keyed(
            "SELECT z.layout_id AS k,
                    SUM(CASE WHEN z.kind = 'STANDING' THEN COALESCE(z.capacity, 0)
                             ELSE (SELECT COUNT(*) FROM seat s WHERE s.zone_id = z.id AND s.is_blocked = 0) END) AS capacity
               FROM zone z WHERE z.layout_id IN (:ids) GROUP BY z.layout_id",
            array_values(array_unique(array_column($performances, 'layout_id'))),
        );

        $byEvent = [];
        foreach ($performances as $p) {
            $id = (string) $p['id'];
            $startsAt = self::utc((string) $p['starts_at']);
            $status = null !== $p['cancelled_at'] ? 'CANCELLED' : ($startsAt <= $now ? 'PAST' : (string) $p['status']);
            $byEvent[(string) $p['event_id']][] = new StudioPerformanceView(
                Uuid::fromBinary($id)->toRfc4122(),
                $startsAt->format(\DATE_ATOM),
                self::utc((string) $p['doors_at'])->format(\DATE_ATOM),
                self::utc((string) $p['sales_open_at'])->format(\DATE_ATOM),
                self::utc((string) $p['sales_close_at'])->format(\DATE_ATOM),
                $status,
                null !== $p['published_at'],
                null === $p['cancel_reason'] ? null : (string) $p['cancel_reason'],
                Uuid::fromBinary((string) $p['layout_id'])->toRfc4122(),
                (string) $p['layout_name'],
                (int) ($sales[$id]['sold'] ?? 0),
                (int) ($capacity[(string) $p['layout_id']]['capacity'] ?? 0),
                (int) ($sales[$id]['revenue'] ?? 0),
                (int) ($priced[$id]['cells'] ?? 0),
            );
        }

        return array_map(static function (array $e) use ($byEvent): StudioEventView {
            $performances = $byEvent[(string) $e['id']] ?? [];
            $live = array_filter($performances, static fn (StudioPerformanceView $p): bool => 'CANCELLED' !== $p->status);

            return new StudioEventView(
                Uuid::fromBinary((string) $e['id'])->toRfc4122(),
                (string) $e['slug'],
                (string) $e['title'],
                null === $e['subtitle'] ? null : (string) $e['subtitle'],
                (string) $e['category'],
                EventCategory::from((string) $e['category'])->label(),
                (string) $e['description'],
                (int) $e['duration_minutes'],
                null === $e['age_rating'] ? null : (int) $e['age_rating'],
                null === $e['poster_path'] ? null : (string) $e['poster_path'],
                (string) $e['status'],
                array_sum(array_map(static fn (StudioPerformanceView $p): int => $p->sold, $live)),
                array_sum(array_map(static fn (StudioPerformanceView $p): int => $p->capacity, $live)),
                array_sum(array_map(static fn (StudioPerformanceView $p): int => $p->revenueCents, $performances)),
                $performances,
            );
        }, $events);
    }

    /**
     * @param list<mixed> $ids binary ids
     *
     * @return array<string, array<string, mixed>>
     */
    private function keyed(string $sql, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $out = [];
        foreach ($this->connection->fetchAllAssociative($sql, ['ids' => $ids], ['ids' => ArrayParameterType::BINARY]) as $row) {
            $out[(string) $row['k']] = $row;
        }

        return $out;
    }

    private static function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
