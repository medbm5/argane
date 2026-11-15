<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\View;

/** One page of the sales table, with totals over the whole filtered set. */
final readonly class SalesPageView
{
    /**
     * @param list<array{id: string, reference: string, createdAt: string, paidAt: ?string, status: string, channel: string, eventTitle: string, performanceStartsAt: string, buyer: ?string, buyerEmail: ?string, seller: ?string, tickets: int, totalCents: int}> $rows
     */
    public function __construct(
        public array $rows,
        public int $page,
        public int $perPage,
        public int $total,
        public int $paidOrders,
        public int $ticketsSold,
        public int $revenueCents,
        public string $currency,
    ) {
    }
}
