<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

/**
 * Something sold, was cancelled or refunded. Published by Booking after the fact;
 * consumers (spreadsheet sync, …) rebuild what they need. Carries no data on purpose:
 * listeners read the current state, so out-of-order delivery cannot corrupt them.
 */
final readonly class SalesChanged
{
    public function __construct(
        public string $reason,
        public float $at,
    ) {
    }
}
