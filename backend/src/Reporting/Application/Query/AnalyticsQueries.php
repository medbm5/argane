<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query;

use DateTimeImmutable;

/**
 * Read model of the BI dashboard. Revenue is net: the price of tickets still live
 * (valid or scanned), so refunds and cancellations drop out. Days are venue days.
 */
interface AnalyticsQueries
{
    /**
     * Totals over [from, to) and over the previous period of the same length.
     *
     * @return array{revenueCents: int, tickets: int, orders: int, onlineTickets: int, refundedOrders: int, sold: int, capacity: int}
     */
    public function totals(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Tickets and revenue per venue day of payment, every day of the range present.
     *
     * @return list<array{date: string, tickets: int, revenueCents: int}>
     */
    public function daily(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Fill per performance (starting in the range) and per zone.
     *
     * @return list<array{performanceId: string, eventTitle: string, startsAt: string, status: string, sold: int, capacity: int, zones: list<array{code: string, name: string, sold: int, capacity: int}>}>
     */
    public function fill(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Net revenue and tickets per tariff and per channel (tickets paid in the range).
     *
     * @return array{byTariff: list<array{tariff: string, tickets: int, revenueCents: int}>, byChannel: list<array{channel: string, tickets: int, revenueCents: int}>}
     */
    public function revenueBreakdown(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Share of capacity sold N days before the show, N = 60 … 0: the average of past
     * performances (reference) and, when given, one performance's own curve.
     *
     * @return array{reference: list<array{daysBefore: int, rate: float}>, performance: list<array{daysBefore: int, rate: float}>}
     */
    public function bookingCurve(DateTimeImmutable $now, ?string $performanceId): array;

    /**
     * Sell-through rate of every seat of a layout over the performances that used it.
     *
     * @return array{layoutId: string, performances: int, seats: list<array{label: string, zone: string, x: float, y: float, rate: float}>}
     */
    public function seatHeatmap(string $layoutId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Upcoming on-sale performances selling clearly slower than past ones at the same
     * distance from the show.
     *
     * @return list<array{performanceId: string, eventTitle: string, startsAt: string, daysLeft: int, rate: float, expected: float, sold: int, capacity: int}>
     */
    public function atRisk(DateTimeImmutable $now): array;
}
