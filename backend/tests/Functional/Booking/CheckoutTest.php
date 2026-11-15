<?php

declare(strict_types=1);

namespace App\Tests\Functional\Booking;

use App\Booking\Application\CheckoutService;
use App\Booking\Application\HoldService;
use App\Booking\Domain\Model\Order;
use App\Booking\Domain\Model\OrderStatus;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\User;
use App\Payment\Application\PaymentGateway;
use App\Pricing\Domain\TariffRepository;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\FakePaymentGateway;
use App\Tests\Support\SalesFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class CheckoutTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private MockClock $clock;
    private Performance $performance;
    private Connection $db;
    private FakePaymentGateway $stripe;
    private User $alice;
    /** @var array<string, string> */
    private array $seats;
    /** @var array<string, string> tariff code → id */
    private array $tariffs = [];
    private string $fosse;

    protected function setUp(): void
    {
        $this->clock = new MockClock(CatalogFactory::NOW);
        Clock::set($this->clock);
        $this->client = self::createClient();
        self::stubClerk($this->client);
        $container = self::getContainer();
        $this->performance = SalesFactory::onSale($container);
        $this->db = $container->get(Connection::class);
        $gateway = $container->get(PaymentGateway::class);
        self::assertInstanceOf(FakePaymentGateway::class, $gateway, 'tests never call Stripe');
        $this->stripe = $gateway;
        $this->seats = SalesFactory::seatIds($this->db, $this->performance);
        $this->fosse = SalesFactory::zoneId($this->performance, 'FOSSE');
        foreach ($container->get(TariffRepository::class)->ofVenue($this->performance->event()->venue()) as $tariff) {
            $this->tariffs[$tariff->code()] = $tariff->id()->toRfc4122();
        }
        $this->alice = self::createUser('user_alice');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function call(string $method, string $uri, array $body = [], ?User $as = null, int $status = 200): array
    {
        $this->client->jsonRequest($method, $uri, $body, null === $as ? [] : self::bearer($as));
        self::assertResponseStatusCodeSame($status, (string) $this->client->getResponse()->getContent());

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    /**
     * Alice holds A-1, A-2 and 2 standing places.
     *
     * @param list<string> $seatLabels
     *
     * @return array<mixed>
     */
    private function hold(?User $user = null, array $seatLabels = ['A-1', 'A-2'], int $standing = 2): array
    {
        return $this->call('POST', '/api/performances/'.$this->performance->id().'/holds', [
            'seatIds' => array_map(fn (string $l): string => $this->seats[$l], $seatLabels),
            'standing' => $standing > 0 ? [['zoneId' => $this->fosse, 'quantity' => $standing]] : [],
        ], $user ?? $this->alice, 201);
    }

    /**
     * @param array<mixed> $hold
     *
     * @return array<mixed>
     */
    private function checkout(array $hold, string $first = 'FULL', string $second = 'REDUCED', string $standing = 'FULL', int $status = 201, ?User $user = null): array
    {
        $seats = [];
        foreach ($hold['seatIds'] as $i => $seatId) {
            $seats[] = ['seatId' => $seatId, 'tariffId' => $this->tariffs[0 === $i ? $first : $second]];
        }

        return $this->call('POST', '/api/holds/'.$hold['id'].'/checkout', [
            'seats' => $seats,
            'standing' => [] === $hold['standing'] ? [] : [['zoneId' => $this->fosse, 'tariffId' => $this->tariffs[$standing], 'quantity' => $hold['standing'][0]['quantity']]],
        ], $user ?? $this->alice, $status);
    }

    private function webhook(string $type, string $intentId, string $eventId = 'evt_1', string $signature = 'valid'): string
    {
        $this->client->request('POST', '/api/webhooks/stripe', server: ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], content: (string) json_encode(['id' => $eventId, 'type' => $type, 'intent' => $intentId]));

        return (string) $this->client->getResponse()->getContent();
    }

    private function intentOf(string $orderId): string
    {
        return (string) $this->db->fetchOne('SELECT provider_ref FROM payment WHERE order_id = ? ORDER BY created_at DESC LIMIT 1', [Uuid::fromString($orderId)->toBinary()], [ParameterType::BINARY]);
    }

    private function seatStatus(string $label): string
    {
        return (string) $this->db->fetchOne('SELECT status FROM performance_seat WHERE id = ?', [Uuid::fromString($this->seats[$label])->toBinary()], [ParameterType::BINARY]);
    }

    public function testCreatesAPendingOrderWithFrozenPricesAndAPaymentIntent(): void
    {
        $checkout = $this->checkout($this->hold());
        $order = $checkout['order'];

        self::assertSame('PENDING_PAYMENT', $order['status']);
        self::assertSame(SalesFactory::FULL + SalesFactory::REDUCED + 2 * SalesFactory::FULL, $order['totalCents']);
        self::assertMatchesRegularExpression('/^ARG-26-\d{6}$/', $order['reference']);
        self::assertCount(4, $order['tickets']);
        self::assertSame(['PENDING'], array_values(array_unique(array_column($order['tickets'], 'status'))));
        self::assertStringEndsWith('_secret_x', (string) $checkout['clientSecret']);
        self::assertSame($order['totalCents'], $this->stripe->retrieveIntent($this->intentOf($order['id']))->amountCents);
        self::assertSame('HELD', $this->seatStatus('A-1'), 'nothing is sold before payment');
    }

    public function testEveryHeldPlaceNeedsExactlyOneTariff(): void
    {
        $hold = $this->hold();
        $mismatch = $this->call('POST', '/api/holds/'.$hold['id'].'/checkout', [
            'seats' => [['seatId' => $hold['seatIds'][0], 'tariffId' => $this->tariffs['FULL']]],
            'standing' => [['zoneId' => $this->fosse, 'tariffId' => $this->tariffs['FULL'], 'quantity' => 2]],
        ], $this->alice, 422);

        self::assertSame('CHECKOUT_MISMATCH', $mismatch['code']);
    }

    public function testEnforcesTariffRules(): void
    {
        $hold = $this->hold();
        self::assertSame('TARIFF_MIN_QUANTITY', $this->checkout($hold, 'GROUP', 'GROUP', 'GROUP', 422)['code']);
        self::assertSame('TARIFF_CHANNEL_NOT_ALLOWED', $this->checkout($hold, 'INVITATION', 'FULL', 'FULL', 403)['code']);
    }

    public function testTheWebhookSellsThePlacesOnceAndOnlyOnce(): void
    {
        $order = $this->checkout($this->hold())['order'];
        $intent = $this->intentOf($order['id']);

        self::assertSame('ok', $this->webhook('payment_intent.succeeded', $intent));
        self::assertSame('duplicate', $this->webhook('payment_intent.succeeded', $intent));

        $paid = $this->call('GET', '/api/orders/'.$order['id'], as: $this->alice);
        self::assertSame('PAID', $paid['status']);
        self::assertSame(['VALID'], array_values(array_unique(array_column($paid['tickets'], 'status'))));
        self::assertSame('SOLD', $this->seatStatus('A-1'));
        self::assertSame('SOLD', $this->seatStatus('A-2'));
        self::assertSame(2, (int) $this->db->fetchOne('SELECT sold_count FROM performance_zone_stock WHERE zone_id = ?', [Uuid::fromString($this->fosse)->toBinary()], [ParameterType::BINARY]));
        self::assertSame('CONVERTED', $this->db->fetchOne('SELECT status FROM hold WHERE id = ?', [Uuid::fromString((string) $paid['holdId'])->toBinary()], [ParameterType::BINARY]));

        // A fresh event for the same intent is harmless too.
        self::assertSame('ok', $this->webhook('payment_intent.succeeded', $intent, 'evt_2'));
        self::assertSame(2, (int) $this->db->fetchOne('SELECT sold_count FROM performance_zone_stock WHERE zone_id = ?', [Uuid::fromString($this->fosse)->toBinary()], [ParameterType::BINARY]));
    }

    public function testRejectsUnsignedWebhooks(): void
    {
        $this->webhook('payment_intent.succeeded', 'pi_x', 'evt_x', 'forged');

        self::assertResponseStatusCodeSame(400);
    }

    public function testADeclinedCardCanBeRetriedWhileTheHoldLasts(): void
    {
        $order = $this->checkout($this->hold())['order'];
        $intent = $this->intentOf($order['id']);

        $this->webhook('payment_intent.payment_failed', $intent, 'evt_f');
        self::assertSame('FAILED', $this->call('GET', '/api/orders/'.$order['id'], as: $this->alice)['status']);

        $this->webhook('payment_intent.succeeded', $intent, 'evt_s');
        self::assertSame('PAID', $this->call('GET', '/api/orders/'.$order['id'], as: $this->alice)['status']);
    }

    public function testALatePaymentStillWinsPlacesThatAreFree(): void
    {
        $order = $this->checkout($this->hold())['order'];
        $this->clock->sleep(700);

        $this->webhook('payment_intent.succeeded', $this->intentOf($order['id']));

        self::assertSame('PAID', $this->call('GET', '/api/orders/'.$order['id'], as: $this->alice)['status']);
        self::assertSame([], $this->stripe->refunds);
    }

    public function testALatePaymentIsRefundedWhenAPlaceWasTaken(): void
    {
        $order = $this->checkout($this->hold())['order'];
        $this->clock->sleep(700);
        $this->hold(self::createUser('user_bob'), ['A-1'], 0);

        $this->webhook('payment_intent.succeeded', $this->intentOf($order['id']));

        $after = $this->call('GET', '/api/orders/'.$order['id'], as: $this->alice);
        self::assertSame('EXPIRED', $after['status']);
        self::assertCount(1, $this->stripe->refunds);
        self::assertSame($order['totalCents'], $this->stripe->refunds[0]['amount']);
        self::assertSame('HELD', $this->seatStatus('A-1'), 'Bob keeps his seat');
        self::assertNotSame('SOLD', $this->seatStatus('A-2'), 'nothing was half-sold (expired hold, free for reads)');
    }

    public function testReadingAnUnpaidOrderReconcilesWithStripe(): void
    {
        $order = $this->checkout($this->hold())['order'];
        $this->stripe->markSucceeded($this->intentOf($order['id']));

        self::assertSame('PAID', $this->call('GET', '/api/orders/'.$order['id'], as: $this->alice)['status'], 'no webhook needed');
    }

    /**
     * The webhook and the buyer's order page confirm the same payment at the same time.
     * The one that loses the race must see the sale, not mistake it for a lost seat and refund.
     */
    public function testAConfirmationRacingTheWebhookNeverRefundsAPaidOrder(): void
    {
        $order = $this->checkout($this->hold())['order'];
        $intent = $this->intentOf($order['id']);
        $container = self::getContainer();
        $db = $container->get(Connection::class);
        $em = $container->get(EntityManagerInterface::class);

        // This request read the order while it was still unpaid…
        $stale = $em->find(Order::class, Uuid::fromString($order['id']));
        self::assertNotNull($stale);
        self::assertSame(OrderStatus::PENDING_PAYMENT, $stale->status());

        // …while the other one sold the places and committed behind its back.
        $db->executeStatement("UPDATE customer_order SET status = 'PAID' WHERE id = ?", [Uuid::fromString($order['id'])->toBinary()], [ParameterType::BINARY]);
        $db->executeStatement(
            "UPDATE performance_seat SET status = 'SOLD', hold_id = NULL, hold_expires_at = NULL WHERE id IN (?, ?)",
            [Uuid::fromString($this->seats['A-1'])->toBinary(), Uuid::fromString($this->seats['A-2'])->toBinary()],
            [ParameterType::BINARY, ParameterType::BINARY],
        );

        $container->get(CheckoutService::class)->paymentSucceeded($intent);

        self::assertSame([], $this->stripe->refunds, 'no refund for a sale that happened');
        self::assertSame('PAID', $db->fetchOne('SELECT status FROM customer_order WHERE id = ?', [Uuid::fromString($order['id'])->toBinary()], [ParameterType::BINARY]));
    }

    public function testCheckingOutAgainReplacesTheUnpaidOrder(): void
    {
        $hold = $this->hold();
        $first = $this->checkout($hold)['order'];
        $second = $this->checkout($hold, 'REDUCED', 'REDUCED')['order'];

        self::assertSame('CANCELLED', $this->call('GET', '/api/orders/'.$first['id'], as: $this->alice)['status']);
        self::assertSame('PENDING_PAYMENT', $second['status']);
        self::assertContains($this->intentOf($first['id']), $this->stripe->cancelled);
    }

    public function testOrdersAndHoldsArePrivate(): void
    {
        $hold = $this->hold();
        $order = $this->checkout($hold)['order'];
        $mallory = self::createUser('user_mallory');

        $this->call('GET', '/api/orders/'.$order['id'], as: $mallory, status: 404);
        $this->checkout($hold, status: 404, user: $mallory);
    }

    public function testExpiryHousekeepingExpiresUnpaidOrders(): void
    {
        $order = $this->checkout($this->hold())['order'];
        $this->clock->sleep(700);

        self::getContainer()->get(HoldService::class)->expireDue();

        self::assertSame('EXPIRED', $this->db->fetchOne('SELECT status FROM customer_order WHERE id = ?', [Uuid::fromString($order['id'])->toBinary()], [ParameterType::BINARY]));
    }
}
