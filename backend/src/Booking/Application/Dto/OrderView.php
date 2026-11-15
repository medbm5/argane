<?php

declare(strict_types=1);

namespace App\Booking\Application\Dto;

use App\Booking\Domain\Model\Order;
use DateTimeInterface;

/** An order as its buyer sees it (confirmation page, wallet). */
final readonly class OrderView
{
    /**
     * @param list<array{id: string, zone: string, seat: ?string, tariff: string, priceCents: int, status: string, requiresProof: bool}> $tickets
     */
    public function __construct(
        public string $id,
        public string $reference,
        public string $status,
        public string $channel,
        public int $totalCents,
        public string $currency,
        public string $performanceId,
        public string $eventTitle,
        public string $eventSlug,
        public ?string $posterUrl,
        public string $startsAt,
        public string $createdAt,
        public ?string $paidAt,
        public ?string $holdId,
        public array $tickets,
    ) {
    }

    public static function of(Order $order): self
    {
        $performance = $order->performance();
        $event = $performance->event();

        return new self(
            $order->id()->toRfc4122(),
            $order->reference(),
            $order->status()->value,
            $order->channel()->value,
            $order->totalCents(),
            $order->currency(),
            $performance->id()->toRfc4122(),
            $event->title(),
            $event->slug(),
            $event->posterPath(),
            $performance->startsAt()->format(DateTimeInterface::ATOM),
            $order->createdAt()->format(DateTimeInterface::ATOM),
            $order->paidAt()?->format(DateTimeInterface::ATOM),
            $order->hold()?->id()->toRfc4122(),
            array_map(static fn ($t): array => [
                'id' => $t->id()->toRfc4122(),
                'zone' => $t->zoneLabel(),
                'seat' => $t->seatLabel(),
                'tariff' => $t->tariffLabel(),
                'priceCents' => $t->unitPriceCents(),
                'status' => $t->status()->value,
                'requiresProof' => $t->requiresProof(),
            ], $order->tickets()),
        );
    }
}
