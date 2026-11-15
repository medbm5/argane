<?php

declare(strict_types=1);

namespace App\Payment\Application\Dto;

/** A verified webhook, reduced to what the booking flow needs. */
final readonly class GatewayEvent
{
    public const string INTENT_SUCCEEDED = 'payment_intent.succeeded';
    public const string INTENT_FAILED = 'payment_intent.payment_failed';

    public function __construct(
        public string $id,
        public string $type,
        public ?string $intentId,
        public int $amountCents = 0,
        public ?string $failureMessage = null,
    ) {
    }
}
