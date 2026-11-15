<?php

declare(strict_types=1);

namespace App\Payment\Domain;

use App\Booking\Domain\Model\Order;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Model\Refund;
use DateTimeImmutable;

interface PaymentRepository
{
    public function save(Payment $payment): void;

    public function findByProviderRef(string $providerRef): ?Payment;

    /** The most recent payment attempt of an order. */
    public function latestOf(Order $order): ?Payment;

    public function saveRefund(Refund $refund): void;

    /**
     * @return list<Refund>
     */
    public function refundsOf(Order $order): array;

    /** Records a webhook id; false when it was already processed (idempotency). */
    public function recordWebhook(string $eventId, string $type, DateTimeImmutable $now): bool;

    /** Undoes recordWebhook when processing failed, so the sender's retry is handled. */
    public function forgetWebhook(string $eventId): void;
}
