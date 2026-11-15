<?php

declare(strict_types=1);

namespace App\Catalog\Application\Message;

/**
 * Published when an organiser cancels a performance. Handled asynchronously by Booking
 * (refund every order) and later by notifications; Catalog does not know who listens.
 */
final readonly class PerformanceCancelled
{
    public function __construct(
        public string $performanceId,
        public ?string $cancelledBy,
        public string $reason,
    ) {
    }
}
