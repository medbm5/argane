<?php

declare(strict_types=1);

namespace App\Booking\Application;

use App\Booking\Application\Dto\OrderView;
use App\Booking\Domain\Model\Order;
use App\Booking\Domain\Model\OrderStatus;
use App\Booking\Domain\OrderRepository;
use App\Catalog\Domain\InventoryRepository;
use App\Payment\Application\RefundIssuer;
use App\Payment\Domain\Model\Refund;
use App\Payment\Domain\Model\RefundStatus;
use App\Shared\Application\Message\SalesChanged;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\SalesChannel;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\Uuid;

/**
 * Cancelling sales: by the buyer (online orders, up to 48 h before the show; places go
 * back on sale) or because the performance itself is cancelled (everything refunded).
 * Tickets are void immediately; the order is REFUNDED once the money is back.
 */
final readonly class CancellationService
{
    public const string CUSTOMER_REASON = 'Annulation par le client';
    public const string PERFORMANCE_REASON = 'Représentation annulée';

    public function __construct(
        private OrderRepository $orders,
        private InventoryRepository $inventory,
        private RefundIssuer $refunds,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        #[Autowire('%app.cancellation_notice_hours%')]
        private int $noticeHours,
        private MessageBusInterface $bus,
    ) {
    }

    public function cancelByCustomer(string $orderId, Uuid $userId): OrderView
    {
        $order = Uuid::isValid($orderId) ? $this->orders->find(Uuid::fromString($orderId)) : null;
        if (null === $order || !$order->customerId()?->equals($userId)) {
            throw NotFoundException::of('Commande', $orderId);
        }
        $now = $this->clock->now();
        $this->assertCancellable($order, $now);

        $this->em->wrapInTransaction(function () use ($order, $now): void {
            foreach ($order->tickets() as $ticket) {
                $seat = $ticket->performanceSeat();
                if (null !== $seat) {
                    $this->inventory->unsellSeat($seat->id(), $ticket->id(), $now);
                } else {
                    $this->inventory->unsellStanding($order->performance()->id(), $ticket->zone()->id(), 1);
                }
                $ticket->cancel();
            }
            $order->cancel($now);
        });

        $this->refund($order, self::CUSTOMER_REASON, $userId, $now);

        $this->salesChanged('cancellation');

        return OrderView::of($order);
    }

    /**
     * Box office: a counter sale is cancelled and the money handed back on the spot
     * (any time before the show). Places go back on sale.
     */
    public function cancelAtCounter(string $orderId, Uuid $staffId): OrderView
    {
        $order = Uuid::isValid($orderId) ? $this->orders->find(Uuid::fromString($orderId)) : null;
        if (null === $order) {
            throw NotFoundException::of('Commande', $orderId);
        }
        $now = $this->clock->now();
        if (SalesChannel::BOX_OFFICE !== $order->channel()) {
            throw BusinessRuleException::because('ONLINE_ORDER', 'Les commandes en ligne s’annulent depuis l’espace client (remboursement par carte).');
        }
        if (OrderStatus::PAID !== $order->status() || $order->performance()->isPast($now)) {
            throw BusinessRuleException::because('ORDER_NOT_CANCELLABLE', 'Cette commande ne peut pas être annulée.', ['status' => $order->status()->value], 409);
        }

        $this->em->wrapInTransaction(function () use ($order, $now): void {
            foreach ($order->tickets() as $ticket) {
                if (null !== $ticket->checkedInAt()) {
                    throw BusinessRuleException::because('ALREADY_CHECKED_IN', 'Un billet de cette commande a déjà été utilisé.', [], 409);
                }
                $seat = $ticket->performanceSeat();
                if (null !== $seat) {
                    $this->inventory->unsellSeat($seat->id(), $ticket->id(), $now);
                } else {
                    $this->inventory->unsellStanding($order->performance()->id(), $ticket->zone()->id(), 1);
                }
                $ticket->cancel();
            }
            $order->cancel($now);
        });

        $refund = $this->refunds->refundOrder($order, 'Annulation au guichet', $staffId, $now);
        if (null !== $refund) {
            $this->refunds->settleAtCounter($refund, $now);
        }
        $this->refund($order, 'Annulation au guichet', $staffId, $now);

        $this->salesChanged('cancellation');

        return OrderView::of($order);
    }

    /** The box office handed back the money of a pending counter refund. */
    public function settleCounterRefund(Refund $refund): void
    {
        $now = $this->clock->now();
        $this->refunds->settleAtCounter($refund, $now);
        $this->refund($refund->order(), $refund->reason(), null, $now);
        $this->salesChanged('counter-refund');
    }

    /** The performance is off: void and refund every paid order. Safe to re-run. */
    public function cancelForPerformance(Uuid $performanceId, ?Uuid $requestedBy): int
    {
        $now = $this->clock->now();
        $orders = $this->orders->paidOfPerformance($performanceId);
        foreach ($orders as $order) {
            foreach ($order->tickets() as $ticket) {
                $ticket->cancel();
            }
            $order->cancel($now);
            $this->em->flush();
            $this->refund($order, self::PERFORMANCE_REASON, $requestedBy, $now);
        }

        if ([] !== $orders) {
            $this->salesChanged('performance-cancelled');
        }

        return \count($orders);
    }

    /** Policy as data for the UI: may this order still be cancelled online? */
    public function cancellableUntil(Order $order): DateTimeImmutable
    {
        return $order->performance()->startsAt()->modify(sprintf('-%d hours', $this->noticeHours));
    }

    private function assertCancellable(Order $order, DateTimeImmutable $now): void
    {
        if (OrderStatus::PAID !== $order->status()) {
            throw BusinessRuleException::because('ORDER_NOT_CANCELLABLE', 'Cette commande ne peut pas être annulée.', ['status' => $order->status()->value], 409);
        }
        if (SalesChannel::ONLINE !== $order->channel()) {
            throw BusinessRuleException::because('COUNTER_ORDER', 'Les billets achetés au guichet s’annulent au guichet.');
        }
        if ($order->performance()->isCancelled()) {
            throw BusinessRuleException::because('PERFORMANCE_CANCELLED', 'Cette représentation est déjà annulée : le remboursement est automatique.', [], 409);
        }
        if ($now > $this->cancellableUntil($order)) {
            throw BusinessRuleException::because('CANCELLATION_TOO_LATE', sprintf('Les annulations sont possibles jusqu’à %d h avant la représentation.', $this->noticeHours), ['hours' => $this->noticeHours]);
        }
        foreach ($order->tickets() as $ticket) {
            if (null !== $ticket->checkedInAt()) {
                throw BusinessRuleException::because('ALREADY_CHECKED_IN', 'Un billet de cette commande a déjà été utilisé.', [], 409);
            }
        }
    }

    private function refund(Order $order, string $reason, ?Uuid $requestedBy, DateTimeImmutable $now): void
    {
        $refund = $this->refunds->refundOrder($order, $reason, $requestedBy, $now);
        if (null === $refund || RefundStatus::SUCCEEDED === $refund->status()) {
            foreach ($order->tickets() as $ticket) {
                $ticket->markRefunded();
            }
            if (null !== $refund) {
                $order->markRefunded($now);
            }
            $this->em->flush();
        }
    }

    private function salesChanged(string $reason): void
    {
        $this->bus->dispatch(new SalesChanged($reason, (float) $this->clock->now()->format('U.u')), [new DelayStamp(15_000)]);
    }
}
