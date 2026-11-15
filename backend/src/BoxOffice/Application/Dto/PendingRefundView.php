<?php

declare(strict_types=1);

namespace App\BoxOffice\Application\Dto;

use App\Payment\Domain\Model\Refund;
use DateTimeInterface;

/** A refund the box office still has to hand back in cash or on the terminal. */
final readonly class PendingRefundView
{
    public function __construct(
        public string $id,
        public string $orderId,
        public string $reference,
        public string $eventTitle,
        public string $startsAt,
        public int $amountCents,
        public string $currency,
        public ?string $paidWith,
        public ?string $buyerName,
        public ?string $buyerEmail,
        public string $reason,
        public string $createdAt,
        public int $tickets,
    ) {
    }

    public static function of(Refund $refund): self
    {
        $order = $refund->order();

        return new self(
            $refund->id()->toRfc4122(),
            $order->id()->toRfc4122(),
            $order->reference(),
            $order->performance()->event()->title(),
            $order->performance()->startsAt()->format(DateTimeInterface::ATOM),
            $refund->amountCents(),
            $order->currency(),
            $refund->payment()?->provider()->value,
            $order->walkInName(),
            $order->walkInEmail(),
            $refund->reason(),
            $refund->createdAt()->format(DateTimeInterface::ATOM),
            \count($order->tickets()),
        );
    }
}
