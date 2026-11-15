<?php

declare(strict_types=1);

namespace App\Booking\Application;

use App\Booking\Domain\Model\Order;
use App\Booking\Domain\Model\OrderStatus;
use App\Catalog\Domain\InventoryRepository;
use App\Payment\Application\PaymentGateway;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Model\Refund;
use App\Payment\Domain\PaymentRepository;
use App\Shared\Application\Message\SalesChanged;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Throwable;

/**
 * Turns a paid order into a sale: every seat goes HELD → SOLD and standing stock is
 * decremented, all or nothing. Inventory is claimed in SQL first; entities are only
 * touched once every place is secured, so a lost place rolls back cleanly.
 *
 * A payment that arrives after the hold expired still wins its places if they are free;
 * otherwise the money is refunded automatically.
 */
final readonly class SaleFinalizer
{
    public const string LATE_REFUND_REASON = 'LATE_PAYMENT_PLACES_TAKEN';

    public function __construct(
        private InventoryRepository $inventory,
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private MessageBusInterface $bus,
    ) {
    }

    /** True when the order is (now) paid with its places sold. Idempotent. */
    public function finalize(Order $order, ?Payment $payment, DateTimeImmutable $now): bool
    {
        if (OrderStatus::PAID === $order->status()) {
            return true;
        }
        if (!\in_array($order->status(), [OrderStatus::PENDING_PAYMENT, OrderStatus::FAILED, OrderStatus::EXPIRED], true)) {
            return false;
        }

        $holdId = $order->hold()?->id();
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $standing = [];
            foreach ($order->tickets() as $ticket) {
                $seat = $ticket->performanceSeat();
                if (null === $seat) {
                    $zoneId = $ticket->zone()->id()->toRfc4122();
                    $standing[$zoneId] = [$ticket->zone(), ($standing[$zoneId][1] ?? 0) + 1];
                    continue;
                }
                if (!$this->inventory->sellSeat($seat->id(), $ticket->id(), $holdId, $now)) {
                    $connection->rollBack();

                    return false;
                }
            }
            foreach ($standing as [$zone, $quantity]) {
                if (!$this->inventory->sellStanding($order->performance()->id(), $zone->id(), $quantity, $holdId, $now)) {
                    $connection->rollBack();

                    return false;
                }
            }

            foreach ($order->tickets() as $ticket) {
                $ticket->activate();
            }
            $order->markPaid($now);
            $payment?->succeed($now);
            $order->hold()?->convert();
            $this->em->flush();
            $connection->commit();
            $this->bus->dispatch(new SalesChanged('sale', (float) $now->format('U.u')), [new DelayStamp(15_000)]);
        } catch (Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }

        return true;
    }

    /** The places went to someone else while the buyer was paying: give the money back. */
    public function refundLatePayment(Order $order, Payment $payment, DateTimeImmutable $now): Refund
    {
        if (OrderStatus::EXPIRED !== $order->status()) {
            $order->markExpired();
        }
        foreach ($order->tickets() as $ticket) {
            $ticket->cancel();
        }
        $payment->succeed($now);
        $refund = new Refund($order, $payment, $payment->amountCents(), self::LATE_REFUND_REASON, null, $now);
        $this->payments->saveRefund($refund);

        try {
            $providerRef = $this->gateway->refund((string) $payment->providerRef(), $payment->amountCents(), $refund->idempotencyKey());
            $refund->succeed($providerRef, $now);
        } catch (Throwable $e) {
            // Kept FAILED-free: the refund stays REQUESTED and is retried by support tooling.
            $this->logger->error('Late payment refund failed for {reference}', ['reference' => $order->reference(), 'exception' => $e]);
        }
        $this->em->flush();

        $this->bus->dispatch(new SalesChanged('late-refund', (float) $now->format('U.u')), [new DelayStamp(15_000)]);

        return $refund;
    }
}
