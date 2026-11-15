<?php

declare(strict_types=1);

namespace App\Tests\Functional\Booking;

use App\Booking\Application\Message\RefundCancelledPerformanceHandler;
use App\Booking\Domain\Model\Order;
use App\Catalog\Application\Message\PerformanceCancelled;
use App\Catalog\Application\PerformanceCancellation;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Payment\Application\PaymentGateway;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\FakePaymentGateway;
use App\Tests\Support\Orders;
use App\Tests\Support\SalesFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class CancellationTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private MockClock $clock;
    private Performance $performance;
    private Connection $db;
    private FakePaymentGateway $stripe;
    private User $alice;

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
        self::assertInstanceOf(FakePaymentGateway::class, $gateway);
        $this->stripe = $gateway;
        $this->alice = self::createUser('user_alice');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function post(string $uri, User $as, int $status, array $body = []): array
    {
        $this->client->jsonRequest('POST', $uri, $body, self::bearer($as));
        self::assertResponseStatusCodeSame($status, (string) $this->client->getResponse()->getContent());

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function scalar(string $sql, string $binaryId): mixed
    {
        return $this->db->fetchOne($sql, [$binaryId], [ParameterType::BINARY]);
    }

    private function seatStatus(string $label): string
    {
        $seatId = SalesFactory::seatIds($this->db, $this->performance)[$label];

        return (string) $this->scalar('SELECT status FROM performance_seat WHERE id = ?', \Symfony\Component\Uid\Uuid::fromString($seatId)->toBinary());
    }

    private function fosseSold(): int
    {
        return (int) $this->scalar('SELECT sold_count FROM performance_zone_stock WHERE performance_id = ?', $this->performance->id()->toBinary());
    }

    public function testABuyerCancelsAndIsRefundedAndThePlacesGoBackOnSale(): void
    {
        $order = Orders::paid(self::getContainer(), $this->performance, $this->alice, ['A-1', 'A-2'], standing: 2);
        self::assertSame('SOLD', $this->seatStatus('A-1'));
        self::assertSame(2, $this->fosseSold());

        $after = $this->post('/api/orders/'.$order->id().'/cancel', $this->alice, 200);

        self::assertSame('REFUNDED', $after['status']);
        self::assertSame(['REFUNDED'], array_values(array_unique(array_column($after['tickets'], 'status'))));
        self::assertSame('AVAILABLE', $this->seatStatus('A-1'));
        self::assertSame('AVAILABLE', $this->seatStatus('A-2'));
        self::assertSame(0, $this->fosseSold());
        self::assertCount(1, $this->stripe->refunds);
        self::assertSame($order->totalCents(), $this->stripe->refunds[0]['amount']);

        self::assertSame('ORDER_NOT_CANCELLABLE', $this->post('/api/orders/'.$order->id().'/cancel', $this->alice, 409)['code']);
        self::assertCount(1, $this->stripe->refunds, 'never refunded twice');
    }

    public function testCancellationClosesFortyEightHoursBeforeTheShow(): void
    {
        $order = Orders::paid(self::getContainer(), $this->performance, $this->alice);
        $this->clock->modify('2026-12-10 19:00:01'); // show on 12/12 at 19:00

        self::assertSame('CANCELLATION_TOO_LATE', $this->post('/api/orders/'.$order->id().'/cancel', $this->alice, 422)['code']);
        $this->post('/api/orders/'.$order->id().'/cancel', self::createUser('user_mallory'), 404);
    }

    public function testAFailedRefundLeavesTheOrderCancelledButNotRefunded(): void
    {
        $order = Orders::paid(self::getContainer(), $this->performance, $this->alice);
        $this->stripe->failRefunds = true;

        $after = $this->post('/api/orders/'.$order->id().'/cancel', $this->alice, 200);

        self::assertSame('CANCELLED', $after['status']);
        self::assertSame(['CANCELLED'], array_values(array_unique(array_column($after['tickets'], 'status'))));
        self::assertSame('FAILED', $this->scalar('SELECT status FROM refund WHERE order_id = ?', $order->id()->toBinary()));
    }

    public function testCancellingAPerformanceStopsSalesAndRefundsEveryoneInTheBackground(): void
    {
        $online = Orders::paid(self::getContainer(), $this->performance, $this->alice, ['A-1']);
        $counter = Orders::paid(self::getContainer(), $this->performance, self::createUser('user_bob'), ['B-1']);
        $this->db->executeStatement("UPDATE payment SET provider = 'CASH' WHERE order_id = ?", [$counter->id()->toBinary()], [ParameterType::BINARY]);
        $organizer = self::createUser('user_boss', Role::ORGANIZER);
        $uri = '/api/studio/performances/'.$this->performance->id().'/cancel';

        $this->post($uri, $this->alice, 403, ['reason' => 'x']);
        self::assertSame('REASON_REQUIRED', $this->post($uri, $organizer, 422, ['reason' => ' '])['code']);
        // The HTTP layer is covered above; cancel through the service so the queued message
        // can be inspected (the client resets the in-memory transport after each request).
        self::getContainer()->get(PerformanceCancellation::class)->cancel($this->performance->id()->toRfc4122(), 'Comédien souffrant', $organizer->id());

        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $sent = $transport->getSent();
        self::assertCount(1, $sent, 'refunds are queued for the worker');
        $message = $sent[0]->getMessage();
        self::assertInstanceOf(PerformanceCancelled::class, $message);

        // Sales are closed at once.
        $this->client->jsonRequest('POST', '/api/performances/'.$this->performance->id().'/holds', ['seatIds' => [SalesFactory::seatIds($this->db, $this->performance)['C-1']]], self::bearer($this->alice));
        self::assertResponseStatusCodeSame(422);

        $handler = self::getContainer()->get(RefundCancelledPerformanceHandler::class);
        $handler($message);
        $handler($message); // a retry changes nothing

        $status = fn (Order $o): string => (string) $this->scalar('SELECT status FROM customer_order WHERE id = ?', $o->id()->toBinary());
        self::assertSame('REFUNDED', $status($online));
        self::assertSame('CANCELLED', $status($counter), 'cash is handed back at the counter');
        self::assertSame('AWAITING_COUNTER', $this->scalar('SELECT status FROM refund WHERE order_id = ?', $counter->id()->toBinary()));
        self::assertCount(1, $this->stripe->refunds);
        self::assertSame(0, (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM ticket WHERE performance_id = ? AND status IN ('VALID', 'CHECKED_IN')",
            [$this->performance->id()->toBinary()],
            [ParameterType::BINARY],
        ), 'no ticket of a cancelled performance can be used');
    }
}
