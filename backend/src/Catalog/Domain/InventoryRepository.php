<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Model\Performance;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface InventoryRepository
{
    /**
     * Creates one performance_seat row per seat (blocked seats as BLOCKED) and one
     * performance_zone_stock row per standing zone. Idempotent: existing rows are kept.
     *
     * @return array{seats: int, standing: int} rows created
     */
    public function generate(Performance $performance, DateTimeImmutable $now): array;

    /**
     * @return array{seats: int, standingZones: int}
     */
    public function count(Performance $performance): array;

    /**
     * Atomically marks performance seats HELD for a hold, but only those AVAILABLE or held
     * by an expired hold. One conditional UPDATE: InnoDB re-checks the condition after
     * waiting on the row lock, so two buyers can never both win a seat. Run inside a
     * transaction; the caller rolls back when ids come back.
     *
     * @param list<Uuid> $seatIds performance seat ids
     *
     * @return list<string> ids that could not be held (unknown, taken, blocked)
     */
    public function holdSeats(Uuid $performanceId, array $seatIds, Uuid $holdId, DateTimeImmutable $expiresAt, DateTimeImmutable $now): array;

    /**
     * @return list<string> performance seat ids currently carrying this hold
     */
    public function seatsOfHold(Uuid $holdId): array;

    /** Puts every seat of the hold back on sale; returns how many were freed. */
    public function releaseSeats(Uuid $holdId, DateTimeImmutable $now): int;

    /**
     * Marks a seat SOLD to a ticket if it is still ours to sell: held by this hold, or
     * free (available, or held by an expired hold). Returns false when someone else got
     * it (late payment after expiry). Run inside the finalising transaction.
     */
    public function sellSeat(Uuid $performanceSeatId, Uuid $ticketId, ?Uuid $holdId, DateTimeImmutable $now): bool;

    /**
     * Adds sold standing places if enough remain, not counting this hold's own lines.
     * Locks the stock row; run inside the finalising transaction.
     */
    public function sellStanding(Uuid $performanceId, Uuid $zoneId, int $quantity, ?Uuid $holdId, DateTimeImmutable $now): bool;

    /** A cancelled sale gives its seat back (only if still sold to this ticket). */
    public function unsellSeat(Uuid $performanceSeatId, Uuid $ticketId, DateTimeImmutable $now): void;

    /** A cancelled sale gives standing places back. */
    public function unsellStanding(Uuid $performanceId, Uuid $zoneId, int $quantity): void;

    /** Frees seats whose hold expired (housekeeping: reads already treat them as free). */
    public function releaseExpiredSeats(DateTimeImmutable $now): int;

    /**
     * Locks a standing stock row (SELECT … FOR UPDATE) and returns what is left after sold
     * places and active, unexpired holds. Run inside a transaction.
     *
     * @param Uuid|null $exceptHoldId a hold whose own lines are not counted
     *
     * @return array{capacity: int, remaining: int}|null null if not standing stock of this performance
     */
    public function lockStanding(Uuid $performanceId, Uuid $zoneId, DateTimeImmutable $now, ?Uuid $exceptHoldId = null): ?array;
}
