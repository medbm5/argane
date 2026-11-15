<?php

declare(strict_types=1);

namespace App\Payment\Application;

use App\Booking\Domain\Model\Order;
use App\Payment\Domain\Model\PaymentProvider;
use App\Payment\Domain\Model\PaymentStatus;
use App\Payment\Domain\Model\Refund;
use App\Payment\Domain\Model\RefundStatus;
use App\Payment\Domain\PaymentRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Gives an order's money back, at most once. Card payments online are refunded through
 * the processor (idempotency key = refund id, so a retry never pays twice); cash and
 * card-terminal sales are flagged for the box office to hand back.
 */
final readonly class RefundIssuer
{
    public function __construct(
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    /** Null when nothing was paid (invitations). Returns the existing refund on retries. */
    public function refundOrder(Order $order, string $reason, ?Uuid $requestedBy, DateTimeImmutable $now): ?Refund
    {
        foreach ($this->payments->refundsOf($order) as $existing) {
            if (RefundStatus::FAILED === $existing->status()) {
                return $this->attempt($existing, $now);
            }

            return $existing;
        }

        $payment = $this->payments->latestOf($order);
        if (null === $payment || PaymentStatus::SUCCEEDED !== $payment->status() || 0 === $order->totalCents()) {
            return null;
        }

        $refund = new Refund($order, $payment, $order->totalCents(), $reason, $requestedBy, $now);
        $this->payments->saveRefund($refund);

        return match ($payment->provider()) {
            PaymentProvider::STRIPE => $this->attempt($refund, $now),
            PaymentProvider::CASH, PaymentProvider::CARD_TERMINAL => $this->toCounter($refund),
            default => $this->settleOffline($refund, $now),
        };
    }

    /** The box office handed the money back. */
    public function settleAtCounter(Refund $refund, DateTimeImmutable $now): void
    {
        if (RefundStatus::AWAITING_COUNTER === $refund->status()) {
            $refund->succeed(null, $now);
            $this->em->flush();
        }
    }

    private function attempt(Refund $refund, DateTimeImmutable $now): Refund
    {
        $intentId = $refund->payment()?->providerRef();
        if (null === $intentId) {
            return $this->settleOffline($refund, $now);
        }
        try {
            $refund->succeed($this->gateway->refund($intentId, $refund->amountCents(), $refund->idempotencyKey()), $now);
        } catch (Throwable $e) {
            $this->logger->error('Refund {refund} failed', ['refund' => $refund->id()->toRfc4122(), 'exception' => $e]);
            $refund->fail($e->getMessage(), $now);
        }
        $this->em->flush();

        return $refund;
    }

    private function toCounter(Refund $refund): Refund
    {
        $refund->awaitCounter();
        $this->em->flush();

        return $refund;
    }

    /** Demo data and payment-less providers: nothing to call. */
    private function settleOffline(Refund $refund, DateTimeImmutable $now): Refund
    {
        $refund->succeed('offline-'.$refund->id()->toBase58(), $now);
        $this->em->flush();

        return $refund;
    }
}
