<?php

declare(strict_types=1);

namespace App\BoxOffice\Application;

use App\Booking\Application\CancellationService;
use App\Booking\Application\CheckoutService;
use App\Booking\Application\Dto\CheckoutRequest;
use App\Booking\Application\Dto\OrderView;
use App\Booking\Domain\OrderRepository;
use App\BoxOffice\Application\Dto\CounterPerformanceView;
use App\BoxOffice\Application\Dto\CounterSaleRequest;
use App\BoxOffice\Application\Dto\PendingRefundView;
use App\Catalog\Application\Query\CatalogQueries;
use App\Payment\Domain\Model\PaymentProvider;
use App\Payment\Domain\Model\RefundStatus;
use App\Payment\Domain\PaymentRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\VenueTime;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The counter: what is on today, selling to walk-ins (cash, card terminal, invitation),
 * cancelling counter sales and handing back pending refunds.
 */
final readonly class BoxOfficeService
{
    public const int DAYS_AHEAD = 14;

    public function __construct(
        private CatalogQueries $catalog,
        private CheckoutService $checkout,
        private CancellationService $cancellations,
        private OrderRepository $orders,
        private PaymentRepository $payments,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<CounterPerformanceView>
     */
    public function performances(): array
    {
        $now = $this->clock->now();
        $today = VenueTime::toVenue($now)->setTime(0, 0);
        $views = [];
        foreach ($this->catalog->performancesBetween($today, $today->modify(sprintf('+%d days', self::DAYS_AHEAD)), $now) as $p) {
            $startsAt = new DateTimeImmutable($p->startsAt);
            $views[] = new CounterPerformanceView(
                $p->id,
                $p->eventTitle,
                $p->eventSlug,
                $p->categoryLabel,
                $p->posterUrl,
                $p->startsAt,
                $p->status,
                \in_array($p->status, ['ON_SALE', 'SALES_CLOSED'], true) && new DateTimeImmutable($p->salesOpenAt) <= $now && $now < $startsAt,
                $p->isOnSale,
                $p->remaining,
                $p->capacity,
                $p->layoutName,
            );
        }

        return $views;
    }

    /**
     * @param list<string> $roles
     */
    public function sell(string $holdId, Uuid $sellerId, array $roles, CounterSaleRequest $request): OrderView
    {
        return $this->checkout->sellAtCounter(
            $holdId,
            $sellerId,
            $roles,
            new CheckoutRequest($request->seats, $request->standing, $request->reason),
            PaymentProvider::from($request->method),
            $request->buyerName,
            $request->buyerEmail,
        );
    }

    public function findByReference(string $reference): OrderView
    {
        $order = $this->orders->findByReference(trim($reference)) ?? throw NotFoundException::of('Commande', $reference);

        return OrderView::of($order);
    }

    public function cancel(string $orderId, Uuid $staffId): OrderView
    {
        return $this->cancellations->cancelAtCounter($orderId, $staffId);
    }

    /**
     * @return list<PendingRefundView>
     */
    public function pendingRefunds(): array
    {
        return array_map(PendingRefundView::of(...), $this->payments->awaitingCounter());
    }

    public function settleRefund(string $refundId): PendingRefundView
    {
        $refund = Uuid::isValid($refundId) ? $this->payments->findRefund(Uuid::fromString($refundId)) : null;
        if (null === $refund || RefundStatus::AWAITING_COUNTER !== $refund->status()) {
            throw NotFoundException::of('Remboursement en attente', $refundId);
        }
        $this->cancellations->settleCounterRefund($refund);

        return PendingRefundView::of($refund);
    }
}
