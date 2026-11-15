<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure;

use App\Catalog\Application\Query\CatalogQueries;
use App\Catalog\Application\Query\View\EventDetailView;
use App\Catalog\Application\Query\View\EventListItemView;
use App\Catalog\Application\Query\View\PerformanceDetailView;
use App\Catalog\Application\Query\View\PerformanceSummaryView;
use App\Catalog\Domain\Model\EventCategory;
use App\Shared\Domain\VenueTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * SQL read model for the public catalog (no ORM hydration on hot pages).
 */
final readonly class DbalCatalogQueries implements CatalogQueries
{
    private const string PERFORMANCE_COLUMNS = 'p.id, p.event_id, p.starts_at, p.doors_at, p.status, p.sales_open_at, p.sales_close_at,
        p.cancel_reason, p.layout_id, l.name AS layout_name';

    private string $currency;

    public function __construct(
        private Connection $connection,
        private AvailabilityCounter $availability,
        #[Autowire('%app.currency%')]
        string $currency,
    ) {
        $this->currency = strtoupper($currency);
    }

    public function listEvents(DateTimeImmutable $now, ?EventCategory $category = null, ?string $month = null): array
    {
        $sql = 'SELECT '.self::PERFORMANCE_COLUMNS.'
                  FROM performance p
                  JOIN event e ON e.id = p.event_id
                  JOIN hall_layout l ON l.id = p.layout_id
                 WHERE e.status = :published AND p.published_at IS NOT NULL
                   AND p.status <> :cancelled AND p.starts_at > :now';
        $params = ['published' => 'PUBLISHED', 'cancelled' => 'CANCELLED', 'now' => self::sql($now)];
        if (null !== $category) {
            $sql .= ' AND e.category = :category';
            $params['category'] = $category->value;
        }
        if (null !== $month && 1 === preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month)) {
            $from = VenueTime::fromVenueLocal($month.'-01 00:00:00');
            $params['from'] = self::sql($from);
            $params['to'] = self::sql(VenueTime::fromVenueLocal(VenueTime::toVenue($from)->modify('first day of next month')->format('Y-m-d').' 00:00:00'));
            $sql .= ' AND p.starts_at >= :from AND p.starts_at < :to';
        }
        $rows = $this->connection->fetchAllAssociative($sql.' ORDER BY p.starts_at', $params);

        $byEvent = [];
        foreach ($rows as $row) {
            $byEvent[(string) $row['event_id']][] = $row;
        }
        if ([] === $byEvent) {
            return [];
        }

        $events = $this->events(array_keys($byEvent));
        $summaries = $this->summaries($rows, $now);

        $items = [];
        foreach ($byEvent as $eventId => $performanceRows) {
            $event = $events[$eventId];
            $eventSummaries = array_map(static fn (array $r): PerformanceSummaryView => $summaries[(string) $r['id']], $performanceRows);
            $items[] = new EventListItemView(
                Uuid::fromBinary($eventId)->toRfc4122(),
                (string) $event['slug'],
                (string) $event['title'],
                self::nullableString($event['subtitle']),
                (string) $event['category'],
                EventCategory::from((string) $event['category'])->label(),
                self::nullableString($event['poster_path']),
                (int) $event['duration_minutes'],
                null === $event['age_rating'] ? null : (int) $event['age_rating'],
                \count($performanceRows),
                self::minPrice($eventSummaries),
                $this->currency,
                $eventSummaries[0] ?? null,
            );
        }

        return $items;
    }

    public function eventBySlug(string $slug, DateTimeImmutable $now): ?EventDetailView
    {
        $event = $this->connection->fetchAssociative('SELECT * FROM event WHERE slug = ? AND status = ?', [$slug, 'PUBLISHED']);
        if (false === $event) {
            return null;
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::PERFORMANCE_COLUMNS.'
               FROM performance p JOIN hall_layout l ON l.id = p.layout_id
              WHERE p.event_id = ? AND p.published_at IS NOT NULL
           ORDER BY p.starts_at',
            [$event['id']],
        );
        $summaries = array_values($this->summaries($rows, $now));
        $upcoming = array_values(array_filter($summaries, static fn (PerformanceSummaryView $s): bool => 'PAST' !== $s->status));
        $past = array_reverse(array_values(array_filter($summaries, static fn (PerformanceSummaryView $s): bool => 'PAST' === $s->status)));

        return new EventDetailView(
            Uuid::fromBinary((string) $event['id'])->toRfc4122(),
            (string) $event['slug'],
            (string) $event['title'],
            self::nullableString($event['subtitle']),
            (string) $event['category'],
            EventCategory::from((string) $event['category'])->label(),
            (string) $event['description'],
            self::nullableString($event['poster_path']),
            (int) $event['duration_minutes'],
            null === $event['age_rating'] ? null : (int) $event['age_rating'],
            self::minPrice(array_filter($upcoming, static fn (PerformanceSummaryView $s): bool => 'CANCELLED' !== $s->status)),
            $this->currency,
            [...$upcoming, ...$past],
        );
    }

    public function performancesBetween(DateTimeImmutable $from, DateTimeImmutable $to, DateTimeImmutable $now): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT p.id FROM performance p JOIN event e ON e.id = p.event_id
              WHERE p.published_at IS NOT NULL AND e.status = ? AND p.starts_at >= ? AND p.starts_at < ?
           ORDER BY p.starts_at',
            ['PUBLISHED', $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')],
        );

        return array_values(array_filter(array_map(
            fn (mixed $id): ?PerformanceDetailView => $this->performance(Uuid::fromBinary((string) $id)->toRfc4122(), $now),
            $ids,
        )));
    }

    public function performance(string $performanceId, DateTimeImmutable $now): ?PerformanceDetailView
    {
        if (!Uuid::isValid($performanceId)) {
            return null;
        }
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::PERFORMANCE_COLUMNS.', e.slug, e.title, e.subtitle, e.category, e.poster_path, e.duration_minutes, e.age_rating
               FROM performance p
               JOIN event e ON e.id = p.event_id
               JOIN hall_layout l ON l.id = p.layout_id
              WHERE p.id = ? AND p.published_at IS NOT NULL AND e.status = ?',
            [Uuid::fromString($performanceId)->toBinary(), 'PUBLISHED'],
        );
        if (false === $row) {
            return null;
        }
        $summary = $this->summaries([$row], $now)[(string) $row['id']];
        $startsAt = self::utc((string) $row['starts_at']);

        return new PerformanceDetailView(
            $summary->id,
            Uuid::fromBinary((string) $row['event_id'])->toRfc4122(),
            (string) $row['slug'],
            (string) $row['title'],
            self::nullableString($row['subtitle']),
            (string) $row['category'],
            EventCategory::from((string) $row['category'])->label(),
            self::nullableString($row['poster_path']),
            (int) $row['duration_minutes'],
            null === $row['age_rating'] ? null : (int) $row['age_rating'],
            $summary->startsAt,
            $summary->doorsAt,
            $startsAt->modify(sprintf('+%d minutes', (int) $row['duration_minutes']))->format(\DATE_ATOM),
            self::utc((string) $row['sales_open_at'])->format(\DATE_ATOM),
            self::utc((string) $row['sales_close_at'])->format(\DATE_ATOM),
            $summary->status,
            $summary->isOnSale,
            self::nullableString($row['cancel_reason']),
            $summary->remaining,
            $summary->capacity,
            $summary->fromPriceCents,
            $this->currency,
            Uuid::fromBinary((string) $row['layout_id'])->toRfc4122(),
            $summary->layoutName,
        );
    }

    /**
     * @param list<array<string, mixed>> $rows performance rows (PERFORMANCE_COLUMNS)
     *
     * @return array<string, PerformanceSummaryView> keyed by binary id
     */
    private function summaries(array $rows, DateTimeImmutable $now): array
    {
        $ids = array_map(static fn (array $r): string => (string) $r['id'], $rows);
        $availability = $this->availability->count($ids, $now);
        $prices = $this->fromPrices($ids);

        $summaries = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $startsAt = self::utc((string) $row['starts_at']);
            $remaining = $availability[$id]['remaining'] ?? 0;
            $status = (string) $row['status'];
            $onSale = 'ON_SALE' === $status
                && self::utc((string) $row['sales_open_at']) <= $now
                && $now < self::utc((string) $row['sales_close_at'])
                && $now < $startsAt
                && $remaining > 0;
            $display = match (true) {
                'CANCELLED' === $status => 'CANCELLED',
                $startsAt <= $now => 'PAST',
                0 === $remaining => 'SOLD_OUT',
                default => $status,
            };

            $summaries[$id] = new PerformanceSummaryView(
                Uuid::fromBinary($id)->toRfc4122(),
                $startsAt->format(\DATE_ATOM),
                self::utc((string) $row['doors_at'])->format(\DATE_ATOM),
                $display,
                $onSale,
                $remaining,
                $availability[$id]['capacity'] ?? 0,
                $prices[$id] ?? null,
                (string) $row['layout_name'],
            );
        }

        return $summaries;
    }

    /**
     * Lowest price a customer can pay online for a single ticket
     * (excludes free tariffs and tariffs with a minimum quantity, e.g. groups).
     *
     * @param list<string> $ids binary ids
     *
     * @return array<string, int>
     */
    private function fromPrices(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        /** @var array<string, int|string> $rows */
        $rows = $this->connection->fetchAllKeyValue(
            "SELECT pp.performance_id, MIN(pp.amount_cents)
               FROM performance_price pp
               JOIN tariff t ON t.id = pp.tariff_id
              WHERE pp.performance_id IN (:ids)
                AND t.active = 1 AND t.is_free = 0 AND t.min_quantity = 1
                AND JSON_CONTAINS(t.channels, '\"ONLINE\"')
           GROUP BY pp.performance_id",
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY],
        );

        return array_map('intval', $rows);
    }

    /**
     * @param list<string> $ids binary event ids
     *
     * @return array<string, array<string, mixed>>
     */
    private function events(array $ids): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM event WHERE id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY],
        );
        $byId = [];
        foreach ($rows as $row) {
            $byId[(string) $row['id']] = $row;
        }

        return $byId;
    }

    /**
     * @param iterable<PerformanceSummaryView> $summaries
     */
    private static function minPrice(iterable $summaries): ?int
    {
        $min = null;
        foreach ($summaries as $summary) {
            if (null !== $summary->fromPriceCents && (null === $min || $summary->fromPriceCents < $min)) {
                $min = $summary->fromPriceCents;
            }
        }

        return $min;
    }

    private static function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private static function sql(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function nullableString(mixed $value): ?string
    {
        return null === $value || '' === $value ? null : (string) $value;
    }
}
