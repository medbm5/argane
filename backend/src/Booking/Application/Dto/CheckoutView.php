<?php

declare(strict_types=1);

namespace App\Booking\Application\Dto;

/** What the payment step needs: the order and the client secret for Stripe Elements. */
final readonly class CheckoutView
{
    public function __construct(
        public OrderView $order,
        /** Null when nothing is due (all invitations): the order is already paid. */
        public ?string $clientSecret,
        public string $holdExpiresAt,
    ) {
    }
}
