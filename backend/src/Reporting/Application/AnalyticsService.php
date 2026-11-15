<?php

declare(strict_types=1);

namespace App\Reporting\Application;

use App\Reporting\Application\Query\AnalyticsQueries;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/** The dashboard's numbers: KPIs against the previous period of the same length. */
final readonly class AnalyticsService
{
    public function __construct(
        private AnalyticsQueries $queries,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{from: string, to: string, kpis: array<string, array{value: float|int, previous: float|int, delta: float|null}>, daily: list<array{date: string, tickets: int, revenueCents: int}>}
     */
    public function overview(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $length = $to->getTimestamp() - $from->getTimestamp();
        $previousFrom = $from->modify(sprintf('-%d seconds', $length));
        $now = self::kpis($this->queries->totals($from, $to));
        $before = self::kpis($this->queries->totals($previousFrom, $from));

        $kpis = [];
        foreach ($now as $name => $value) {
            $previous = $before[$name];
            $kpis[$name] = [
                'value' => $value,
                'previous' => $previous,
                // Relative change; null when there is nothing to compare with.
                'delta' => 0.0 === (float) $previous ? null : round(($value - $previous) / abs($previous), 4),
            ];
        }

        return [
            'from' => $from->format(\DATE_ATOM),
            'to' => $to->format(\DATE_ATOM),
            'kpis' => $kpis,
            'daily' => $this->queries->daily($from, $to),
        ];
    }

    /**
     * Default period: the last 30 days, up to now.
     *
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    public function defaultRange(): array
    {
        $now = $this->clock->now();

        return [$now->modify('-30 days'), $now];
    }

    /**
     * @param array{revenueCents: int, tickets: int, orders: int, onlineTickets: int, refundedOrders: int, sold: int, capacity: int} $t
     *
     * @return array{revenueCents: int, tickets: int, orders: int, averageBasketCents: int, fillRate: float, onlineShare: float, refundedOrders: int}
     */
    private static function kpis(array $t): array
    {
        return [
            'revenueCents' => $t['revenueCents'],
            'tickets' => $t['tickets'],
            'orders' => $t['orders'],
            'averageBasketCents' => $t['orders'] > 0 ? intdiv($t['revenueCents'], $t['orders']) : 0,
            'fillRate' => $t['capacity'] > 0 ? round($t['sold'] / $t['capacity'], 4) : 0.0,
            'onlineShare' => $t['tickets'] > 0 ? round($t['onlineTickets'] / $t['tickets'], 4) : 0.0,
            'refundedOrders' => $t['refundedOrders'],
        ];
    }
}
