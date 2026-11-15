<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

/**
 * An order was cancelled. `cause` says who decided: the buyer, the theatre (the
 * performance is off) or the box office; `reason` is the text shown to the buyer.
 */
final readonly class OrderCancelled
{
    public const string BY_CUSTOMER = 'customer';
    public const string PERFORMANCE_CANCELLED = 'performance';
    public const string AT_COUNTER = 'counter';

    public function __construct(
        public string $orderId,
        public string $cause,
        public ?string $reason = null,
    ) {
    }
}
