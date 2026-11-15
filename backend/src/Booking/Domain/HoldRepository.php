<?php

declare(strict_types=1);

namespace App\Booking\Domain;

use App\Booking\Domain\Model\Hold;
use App\Catalog\Domain\Model\Performance;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface HoldRepository
{
    public function save(Hold $hold): void;

    public function find(Uuid $id): ?Hold;

    /**
     * Holds of this user on this performance still in ACTIVE status (possibly past expiry).
     *
     * @return list<Hold>
     */
    public function activeOf(Uuid $userId, Performance $performance): array;

    /** Marks ACTIVE holds past their expiry as EXPIRED; returns how many. */
    public function expireDue(DateTimeImmutable $now): int;

    /** Holds created by this user since the given instant (rate limiting, audits). */
    public function countCreatedSince(Uuid $userId, DateTimeImmutable $since): int;
}
