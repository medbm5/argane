<?php

declare(strict_types=1);

namespace App\Booking\Domain;

use App\Booking\Domain\Model\Hold;
use App\Booking\Domain\Model\Order;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface OrderRepository
{
    public function save(Order $order): void;

    public function find(Uuid $id): ?Order;

    public function findByReference(string $reference): ?Order;

    /**
     * Unpaid (PENDING_PAYMENT or FAILED) orders created from this hold.
     *
     * @return list<Order>
     */
    public function unpaidOfHold(Hold $hold): array;

    /**
     * The buyer's orders that produced tickets (paid, then possibly cancelled or refunded),
     * soonest performance first.
     *
     * @return list<Order>
     */
    public function ticketedOf(Uuid $customerId): array;

    /** Unpaid orders whose hold expired become EXPIRED; returns how many. */
    public function expireUnpaid(DateTimeImmutable $now): int;
}
