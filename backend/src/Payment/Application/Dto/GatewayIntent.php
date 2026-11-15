<?php

declare(strict_types=1);

namespace App\Payment\Application\Dto;

/** A payment intent as the processor reports it. */
final readonly class GatewayIntent
{
    public const string SUCCEEDED = 'succeeded';
    public const string PROCESSING = 'processing';
    public const string CANCELED = 'canceled';

    public function __construct(
        public string $id,
        public string $status,
        public int $amountCents,
        public ?string $clientSecret = null,
        public ?string $lastError = null,
    ) {
    }

    public function succeeded(): bool
    {
        return self::SUCCEEDED === $this->status;
    }

    /** Card declined: the buyer may retry with another method on the same intent. */
    public function failed(): bool
    {
        return 'requires_payment_method' === $this->status && null !== $this->lastError;
    }
}
