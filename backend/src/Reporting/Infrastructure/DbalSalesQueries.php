<?php

declare(strict_types=1);

namespace App\Reporting\Infrastructure;

use App\Reporting\Application\Query\SalesFilter;
use App\Reporting\Application\Query\SalesQueries;
use App\Reporting\Application\Query\View\SalesPageView;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Read model for the Studio sales table and its CSV export (reads across modules'
 * tables on purpose: reporting, never writes).
 */
final readonly class DbalSalesQueries implements SalesQueries
{
    public function __construct(
        private Connection $connection,
        #[Autowire('%app.currency%')]
        private string $currency,
    ) {
    }

    public function page(SalesFilter $filter): SalesPageView
    {
        [$where, $params, $types] = $this->where($filter);
        $base = 'FROM customer_order o JOIN performance p ON p.id = o.performance_id JOIN event e ON e.id = p.event_id
                 LEFT JOIN app_user c ON c.id = o.customer_id LEFT JOIN app_user s ON s.id = o.seller_id '.$where;

        $totals = $this->connection->fetchAssociative(
            "SELECT COUNT(*) AS total,
                    SUM(o.status IN ('PAID', 'PARTIALLY_REFUNDED')) AS paid,
                    COALESCE(SUM(CASE WHEN o.status IN ('PAID', 'PARTIALLY_REFUNDED') THEN o.total_cents END), 0) AS revenue,
                    COALESCE(SUM((SELECT COUNT(*) FROM ticket t WHERE t.order_id = o.id AND t.status IN ('VALID', 'CHECKED_IN'))), 0) AS tickets
             $base",
            $params,
            $types,
        ) ?: [];

        $perPage = max(1, min(200, $filter->perPage));
        $offset = (max(1, $filter->page) - 1) * $perPage;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT o.id, o.reference, o.created_at, o.paid_at, o.status, o.channel, o.total_cents, e.title, p.starts_at,
                    COALESCE(o.walk_in_name, c.display_name) AS buyer, COALESCE(o.walk_in_email, c.email) AS buyer_email,
                    s.display_name AS seller,
                    (SELECT COUNT(*) FROM ticket t WHERE t.order_id = o.id AND t.status IN ('VALID', 'CHECKED_IN')) AS tickets
             $base ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset",
            $params,
            $types,
        );

        return new SalesPageView(
            array_map(static fn (array $r): array => [
                'id' => Uuid::fromBinary((string) $r['id'])->toRfc4122(),
                'reference' => (string) $r['reference'],
                'createdAt' => self::atom($r['created_at']),
                'paidAt' => null === $r['paid_at'] ? null : self::atom($r['paid_at']),
                'status' => (string) $r['status'],
                'channel' => (string) $r['channel'],
                'eventTitle' => (string) $r['title'],
                'performanceStartsAt' => self::atom($r['starts_at']),
                'buyer' => null === $r['buyer'] ? null : (string) $r['buyer'],
                'buyerEmail' => null === $r['buyer_email'] ? null : (string) $r['buyer_email'],
                'seller' => null === $r['seller'] ? null : (string) $r['seller'],
                'tickets' => (int) $r['tickets'],
                'totalCents' => (int) $r['total_cents'],
            ], $rows),
            max(1, $filter->page),
            $perPage,
            (int) ($totals['total'] ?? 0),
            (int) ($totals['paid'] ?? 0),
            (int) ($totals['tickets'] ?? 0),
            (int) ($totals['revenue'] ?? 0),
            strtoupper($this->currency),
        );
    }

    public function ticketRows(SalesFilter $filter): iterable
    {
        [$where, $params, $types] = $this->where($filter);
        $result = $this->connection->executeQuery(
            "SELECT o.reference, o.created_at, o.status AS order_status, o.channel, e.title, p.starts_at,
                    t.zone_label, t.seat_label, t.tariff_label, t.unit_price_cents, t.status AS ticket_status, t.checked_in_at,
                    COALESCE(o.walk_in_name, c.display_name) AS buyer, COALESCE(o.walk_in_email, c.email) AS buyer_email
               FROM customer_order o JOIN performance p ON p.id = o.performance_id JOIN event e ON e.id = p.event_id
               JOIN ticket t ON t.order_id = o.id
               LEFT JOIN app_user c ON c.id = o.customer_id
             $where
           ORDER BY o.created_at, o.reference, t.seat_label",
            $params,
            $types,
        );
        while (false !== ($r = $result->fetchAssociative())) {
            yield [
                'reference' => (string) $r['reference'],
                'orderedAt' => self::atom($r['created_at']),
                'event' => (string) $r['title'],
                'performance' => self::atom($r['starts_at']),
                'zone' => (string) $r['zone_label'],
                'seat' => null === $r['seat_label'] ? null : (string) $r['seat_label'],
                'tariff' => (string) $r['tariff_label'],
                'channel' => (string) $r['channel'],
                'priceCents' => (int) $r['unit_price_cents'],
                'ticketStatus' => (string) $r['ticket_status'],
                'orderStatus' => (string) $r['order_status'],
                'checkedInAt' => null === $r['checked_in_at'] ? null : self::atom($r['checked_in_at']),
                'buyer' => null === $r['buyer'] ? null : (string) $r['buyer'],
                'buyerEmail' => null === $r['buyer_email'] ? null : (string) $r['buyer_email'],
            ];
        }
    }

    /**
     * Only orders that produced tickets or tried to (unpaid carts are noise here).
     *
     * @return array{string, array<string, mixed>, array<string, ParameterType>}
     */
    private function where(SalesFilter $f): array
    {
        $clauses = ["o.status NOT IN ('PENDING_PAYMENT', 'EXPIRED', 'FAILED')"];
        $params = [];
        $types = [];
        if (null !== $f->eventId && Uuid::isValid($f->eventId)) {
            $clauses[] = 'e.id = :event';
            $params['event'] = Uuid::fromString($f->eventId)->toBinary();
            $types['event'] = ParameterType::BINARY;
        }
        if (null !== $f->performanceId && Uuid::isValid($f->performanceId)) {
            $clauses[] = 'p.id = :performance';
            $params['performance'] = Uuid::fromString($f->performanceId)->toBinary();
            $types['performance'] = ParameterType::BINARY;
        }
        if (null !== $f->channel) {
            $clauses[] = 'o.channel = :channel';
            $params['channel'] = $f->channel;
        }
        if (null !== $f->status) {
            $clauses[] = 'o.status = :status';
            $params['status'] = $f->status;
        }
        if (null !== $f->from) {
            $clauses[] = 'o.created_at >= :from';
            $params['from'] = $f->from->format('Y-m-d H:i:s');
        }
        if (null !== $f->to) {
            $clauses[] = 'o.created_at < :to';
            $params['to'] = $f->to->format('Y-m-d H:i:s');
        }
        if (null !== $f->search && '' !== trim($f->search)) {
            $clauses[] = '(o.reference LIKE :q OR o.walk_in_name LIKE :q OR o.walk_in_email LIKE :q OR c.email LIKE :q OR c.display_name LIKE :q)';
            $params['q'] = '%'.addcslashes(trim($f->search), '%_').'%';
        }

        return ['WHERE '.implode(' AND ', $clauses), $params, $types];
    }

    private static function atom(mixed $value): string
    {
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))->format(\DATE_ATOM);
    }
}
