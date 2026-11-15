<?php

declare(strict_types=1);

namespace App\Shared\Application\Message;

/** An order was paid and its places sold (online or at the counter). */
final readonly class OrderPaid
{
    public function __construct(public string $orderId)
    {
    }
}
