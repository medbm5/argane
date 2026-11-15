<?php

declare(strict_types=1);

namespace App\Booking\Application;

use App\Booking\Application\Dto\CheckoutRequest;
use App\Booking\Application\Dto\CheckoutView;
use App\Booking\Application\Dto\OrderView;
use App\Booking\Domain\HoldRepository;
use App\Booking\Domain\Model\Hold;
use App\Booking\Domain\Model\Order;
use App\Booking\Domain\Model\OrderStatus;
use App\Booking\Domain\Model\Ticket;
use App\Booking\Domain\OrderReferenceGenerator;
use App\Booking\Domain\OrderRepository;
use App\Catalog\Domain\InventoryRepository;
use App\Catalog\Domain\Model\PerformanceSeat;
use App\Payment\Application\PaymentGateway;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Model\PaymentProvider;
use App\Payment\Domain\PaymentRepository;
use App\Pricing\Application\TariffEligibilityPolicy;
use App\Pricing\Domain\Model\PerformancePrice;
use App\Pricing\Domain\PriceRepository;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\NotFoundException;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * From a hold to a payable order: validates a tariff for every held place, freezes prices
 * on tickets, and opens a Stripe PaymentIntent (idempotency key = order id). Payment
 * outcomes arrive by webhook, with a reconcile-on-read fallback.
 */
final readonly class CheckoutService
{
    public function __construct(
        private HoldRepository $holds,
        private OrderRepository $orders,
        private OrderReferenceGenerator $references,
        private InventoryRepository $inventory,
        private PriceRepository $prices,
        private TariffEligibilityPolicy $eligibility,
        private PaymentRepository $payments,
        private PaymentGateway $gateway,
        private SaleFinalizer $finalizer,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        #[Autowire('%app.currency%')]
        private string $currency,
    ) {
    }

    /**
     * @param list<string> $roles the caller's reachable roles
     */
    public function checkout(string $holdId, Uuid $userId, ?string $email, array $roles, CheckoutRequest $request): CheckoutView
    {
        $now = $this->clock->now();
        $hold = $this->ownedHold($holdId, $userId);
        $hold->assertActive($now);
        $performance = $hold->performance();
        $this->assertCoversHold($hold, $request);

        /** @var array<string, array<string, PerformancePrice>> $grid zone id → tariff id → price */
        $grid = [];
        foreach ($this->prices->ofPerformance($performance) as $price) {
            $grid[$price->zone()->id()->toRfc4122()][$price->tariff()->id()->toRfc4122()] = $price;
        }
        $priceFor = static function (string $zoneId, string $tariffId) use ($grid): PerformancePrice {
            return $grid[$zoneId][strtolower($tariffId)]
                ?? throw BusinessRuleException::because('TARIFF_NOT_PRICED', 'Ce tarif n’est pas proposé dans cette zone.', ['zoneId' => $zoneId, 'tariffId' => $tariffId]);
        };

        $order = new Order($this->references->next($now), $performance, $hold->channel(), $this->currency, $now, $userId, null, $hold);
        $perTariff = [];
        foreach ($request->seats as $line) {
            $seat = $this->em->find(PerformanceSeat::class, Uuid::fromString($line['seatId'])) ?? throw NotFoundException::of('Place', $line['seatId']);
            $price = $priceFor($seat->zone()->id()->toRfc4122(), $line['tariffId']);
            (new Ticket($order, $performance, $seat->zone(), $price->tariff(), $price->amountCents(), $seat))->awaitPayment();
            $perTariff[$price->tariff()->id()->toRfc4122()] = [$price->tariff(), ($perTariff[$price->tariff()->id()->toRfc4122()][1] ?? 0) + 1];
        }
        foreach ($request->standing as $line) {
            $price = $priceFor(strtolower($line['zoneId']), $line['tariffId']);
            for ($i = 0; $i < $line['quantity']; ++$i) {
                (new Ticket($order, $performance, $price->zone(), $price->tariff(), $price->amountCents()))->awaitPayment();
            }
            $perTariff[$price->tariff()->id()->toRfc4122()] = [$price->tariff(), ($perTariff[$price->tariff()->id()->toRfc4122()][1] ?? 0) + $line['quantity']];
        }
        foreach ($perTariff as [$tariff, $quantity]) {
            $this->eligibility->assertEligible($tariff, $hold->channel(), $roles, $quantity, $request->reason);
        }
        $order->annotate($request->reason);

        // Validated: one unpaid order per hold, so this checkout replaces any previous one
        // (the buyer went back and changed tariffs).
        foreach ($this->orders->unpaidOfHold($hold) as $previous) {
            $this->abandon($previous);
        }
        $this->orders->save($order);

        if (0 === $order->totalCents()) {
            $this->finalizer->finalize($order, null, $now);

            return new CheckoutView(OrderView::of($order), null, $hold->expiresAt()->format(DateTimeInterface::ATOM));
        }

        $payment = new Payment($order, PaymentProvider::STRIPE, $order->totalCents(), $order->currency(), $now);
        $intent = $this->gateway->createIntent(
            $order->totalCents(),
            $order->currency(),
            'order-'.$order->id()->toRfc4122(),
            ['orderId' => $order->id()->toRfc4122(), 'reference' => $order->reference(), 'holdId' => $hold->id()->toRfc4122()],
            $email,
        );
        $payment->attachProviderRef($intent->id);
        $this->payments->save($payment);

        return new CheckoutView(OrderView::of($order), $intent->clientSecret, $hold->expiresAt()->format(DateTimeInterface::ATOM));
    }

    /**
     * The buyer's order. While unpaid, asks the processor directly so a missed or late
     * webhook never leaves the buyer staring at "pending".
     */
    public function order(string $orderId, Uuid $userId): OrderView
    {
        $order = Uuid::isValid($orderId) ? $this->orders->find(Uuid::fromString($orderId)) : null;
        if (null === $order || !$order->customerId()?->equals($userId)) {
            throw NotFoundException::of('Commande', $orderId);
        }
        if (\in_array($order->status(), [OrderStatus::PENDING_PAYMENT, OrderStatus::FAILED, OrderStatus::EXPIRED], true)) {
            $payment = $this->payments->latestOf($order);
            if (null !== $payment?->providerRef()) {
                try {
                    $intent = $this->gateway->retrieveIntent($payment->providerRef());
                    if ($intent->succeeded()) {
                        $this->paymentSucceeded($intent->id);
                    } elseif ($intent->failed()) {
                        $this->paymentFailed($intent->id);
                    }
                } catch (Throwable $e) {
                    $this->logger->warning('Could not reconcile {reference}', ['reference' => $order->reference(), 'exception' => $e]);
                }
            }
        }

        return OrderView::of($order);
    }

    /** Webhook or reconcile: the money is in. Sell the places, or refund if they are gone. */
    public function paymentSucceeded(string $intentId): void
    {
        $payment = $this->payments->findByProviderRef($intentId);
        if (null === $payment) {
            $this->logger->warning('Payment succeeded for unknown intent {intent}', ['intent' => $intentId]);

            return;
        }
        $order = $payment->order();
        if (OrderStatus::PAID === $order->status() || OrderStatus::CANCELLED === $order->status()) {
            return;
        }
        $now = $this->clock->now();
        if (!$this->finalizer->finalize($order, $payment, $now)) {
            $this->finalizer->refundLatePayment($order, $payment, $now);
        }
    }

    /** Card declined: the order can be retried while the hold lasts. */
    public function paymentFailed(string $intentId): void
    {
        $payment = $this->payments->findByProviderRef($intentId);
        if (null === $payment || OrderStatus::PENDING_PAYMENT !== $payment->order()->status()) {
            return;
        }
        $payment->order()->markFailed();
        $payment->fail($this->clock->now());
        $this->em->flush();
    }

    private function abandon(Order $order): void
    {
        $now = $this->clock->now();
        foreach ($order->tickets() as $ticket) {
            $ticket->cancel();
        }
        $order->cancel($now);
        $payment = $this->payments->latestOf($order);
        if (null !== $payment) {
            $payment->cancel($now);
            if (null !== $payment->providerRef()) {
                try {
                    $this->gateway->cancelIntent($payment->providerRef());
                } catch (Throwable $e) {
                    $this->logger->warning('Could not cancel intent {intent}', ['intent' => $payment->providerRef(), 'exception' => $e]);
                }
            }
        }
        $this->em->flush();
    }

    private function ownedHold(string $holdId, Uuid $userId): Hold
    {
        $hold = Uuid::isValid($holdId) ? $this->holds->find(Uuid::fromString($holdId)) : null;
        if (null === $hold || !$hold->userId()->equals($userId)) {
            throw NotFoundException::of('Réservation', $holdId);
        }

        return $hold;
    }

    /** Every held seat gets exactly one tariff; every standing quantity is fully split. */
    private function assertCoversHold(Hold $hold, CheckoutRequest $request): void
    {
        $held = $this->inventory->seatsOfHold($hold->id());
        $asked = array_map(static fn (array $l): string => strtolower($l['seatId']), $request->seats);
        sort($held);
        sort($asked);

        $heldStanding = [];
        foreach ($hold->standingLines() as $line) {
            $heldStanding[$line->zone()->id()->toRfc4122()] = $line->quantity();
        }
        $askedStanding = [];
        foreach ($request->standing as $line) {
            $zone = strtolower($line['zoneId']);
            $askedStanding[$zone] = ($askedStanding[$zone] ?? 0) + $line['quantity'];
        }
        ksort($heldStanding);
        ksort($askedStanding);

        if ($held !== $asked || $heldStanding !== $askedStanding) {
            throw BusinessRuleException::because('CHECKOUT_MISMATCH', 'Choisissez un tarif pour chacune des places réservées.', ['seatIds' => $held, 'standing' => $heldStanding]);
        }
    }
}
