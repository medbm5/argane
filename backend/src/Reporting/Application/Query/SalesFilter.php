<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query;

use DateTimeImmutable;

/** Sales table filters; null means "any". Dates bound the order creation, venue days. */
final readonly class SalesFilter
{
    public function __construct(
        public ?string $eventId = null,
        public ?string $performanceId = null,
        public ?string $channel = null,
        public ?string $status = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {
    }
}
