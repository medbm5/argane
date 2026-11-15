<?php

declare(strict_types=1);

namespace App\Booking\Application\Dto;

use App\Booking\Domain\Model\Hold;
use DateTimeImmutable;
use DateTimeInterface;

/** A hold as the buyer sees it: what is blocked for them, and until when. */
final readonly class HoldView
{
    /**
     * @param list<string>                               $seatIds  performance seat ids
     * @param list<array{zoneId: string, quantity: int}> $standing
     */
    public function __construct(
        public string $id,
        public string $performanceId,
        public string $status,
        public string $channel,
        public string $expiresAt,
        public int $secondsLeft,
        public array $seatIds,
        public array $standing,
    ) {
    }

    /**
     * @param list<string> $seatIds
     */
    public static function of(Hold $hold, array $seatIds, DateTimeImmutable $now): self
    {
        return new self(
            $hold->id()->toRfc4122(),
            $hold->performance()->id()->toRfc4122(),
            $hold->isExpired($now) ? 'EXPIRED' : $hold->status()->value,
            $hold->channel()->value,
            $hold->expiresAt()->format(DateTimeInterface::ATOM),
            max(0, $hold->expiresAt()->getTimestamp() - $now->getTimestamp()),
            $seatIds,
            array_map(static fn ($line): array => ['zoneId' => $line->zone()->id()->toRfc4122(), 'quantity' => $line->quantity()], $hold->standingLines()),
        );
    }
}
