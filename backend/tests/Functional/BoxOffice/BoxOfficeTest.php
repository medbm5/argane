<?php

declare(strict_types=1);

namespace App\Tests\Functional\BoxOffice;

use App\Booking\Application\Message\RefundCancelledPerformanceHandler;
use App\Catalog\Application\Message\PerformanceCancelled;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Pricing\Domain\TariffRepository;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\Orders;
use App\Tests\Support\SalesFactory;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class BoxOfficeTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private MockClock $clock;
    private Performance $performance;
    private Connection $db;
    private User $counter;
    /** @var array<string, string> */
    private array $seats;
    /** @var array<string, string> */
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
        $this->seats = SalesFactory::seatIds($this->db, $this->performance);
        $this->fosse = SalesFactory::zoneId($this->performance, 'FOSSE');
        foreach ($container->get(TariffRepository::class)->ofVenue($this->performance->event()->venue()) as $tariff) {
            $this->tariffs[$tariff->code()] = $tariff->id()->toRfc4122();
        }
        $this->counter = self::createUser('user_counter', Role::BOX_OFFICE);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function call(string $method, string $uri, User $as, int $status, array $body = []): array
    {
        $this->client->jsonRequest($method, $uri, $body, self::bearer($as));
        self::assertResponseStatusCodeSame($status, (string) $this->client->getResponse()->getContent());

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    /**
     * @param list<string> $labels
     *
     * @return array<mixed>
     */
    private function hold(array $labels, int $standing = 0, ?User $as = null, string $channel = 'BOX_OFFICE', int $status = 201): array
    {
        return $this->call('POST', '/api/performances/'.$this->performance->id().'/holds', $as ?? $this->counter, $status, [
            'seatIds' => array_map(fn (string $l): string => $this->seats[$l], $labels),
            'standing' => $standing > 0 ? [['zoneId' => $this->fosse, 'quantity' => $standing]] : [],
            'channel' => $channel,
        ]);
    }

    /**
     * @param array<mixed>         $hold
     * @param array<string, mixed> $extra
     *
     * @return array<mixed>
     */
    private function sell(array $hold, string $tariff, array $extra = [], int $status = 201): array
    {
        return $this->call('POST', '/api/box-office/holds/'.$hold['id'].'/sell', $this->counter, $status, [
            'seats' => array_map(fn (string $id): array => ['seatId' => $id, 'tariffId' => $this->tariffs[$tariff]], $hold['seatIds']),
            'standing' => [] === $hold['standing'] ? [] : [['zoneId' => $this->fosse, 'tariffId' => $this->tariffs[$tariff], 'quantity' => $hold['standing'][0]['quantity']]],
            ...$extra,
        ]);
    }

    private function scalar(string $sql, string $id): mixed
    {
        return $this->db->fetchOne($sql, [Uuid::fromString($id)->toBinary()], [ParameterType::BINARY]);
    }

    public function testListsTheCountersPerformancesForStaffOnly(): void
    {
        $this->clock->modify('2026-12-05 10:00:00'); // the counter looks two weeks ahead
        $list = $this->call('GET', '/api/box-office/performances', $this->counter, 200);
        $mine = array_values(array_filter($list, fn (array $p): bool => $p['id'] === $this->performance->id()->toRfc4122()))[0] ?? self::fail();

        self::assertTrue($mine['sellable']);
        self::assertSame(61, $mine['remaining']);
        $this->call('GET', '/api/box-office/performances', self::createUser('user_alice'), 403);
    }

    public function testSellsForCashToAWalkInAtOnce(): void
    {
        $order = $this->sell($this->hold(['A-1'], standing: 2), 'FULL', ['method' => 'CASH', 'buyerName' => 'Nadia B.']);

        self::assertSame('PAID', $order['status']);
        self::assertSame('BOX_OFFICE', $order['channel']);
        self::assertSame(3 * SalesFactory::FULL, $order['totalCents']);
        self::assertSame(['VALID'], array_values(array_unique(array_column($order['tickets'], 'status'))));
        self::assertSame('SOLD', $this->scalar('SELECT status FROM performance_seat WHERE id = ?', $this->seats['A-1']));
        self::assertSame('CASH', $this->scalar('SELECT provider FROM payment WHERE order_id = ?', $order['id']));
        self::assertSame('Nadia B.', $this->scalar('SELECT walk_in_name FROM customer_order WHERE id = ?', $order['id']));
    }

    public function testInvitationsNeedTheRoleAndAReason(): void
    {
        $hold = $this->hold(['A-2']);
        self::assertSame('INVITATION_REASON_REQUIRED', $this->sell($hold, 'INVITATION', [], 422)['code']);

        $order = $this->sell($hold, 'INVITATION', ['reason' => 'Presse : Souss Radio']);
        self::assertSame('PAID', $order['status']);
        self::assertSame(0, $order['totalCents']);

        // Customers can neither hold at the counter nor pick the invitation tariff online.
        $this->hold(['B-1'], as: self::createUser('user_alice'), status: 403);
    }

    public function testGroupTariffKeepsItsMinimum(): void
    {
        self::assertSame('TARIFF_MIN_QUANTITY', $this->sell($this->hold(['B-2'], standing: 3), 'GROUP', ['method' => 'CARD_TERMINAL'], 422)['code']);
        self::assertSame('PAID', $this->sell($this->hold([], standing: 10), 'GROUP', ['method' => 'CARD_TERMINAL'])['status']);
    }

    public function testTheCounterSellsUntilCurtainAfterOnlineSalesClosed(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $performance = $em->find(Performance::class, $this->performance->id()) ?? self::fail();
        $performance->reschedule($performance->startsAt(), $performance->doorsAt(), $performance->salesOpenAt(), new DateTimeImmutable('2026-12-12 17:00:00'));
        $em->flush();
        $this->clock->modify('2026-12-12 18:00:00');

        self::assertSame('NOT_ON_SALE', $this->hold(['C-1'], as: self::createUser('user_late'), channel: 'ONLINE', status: 422)['code']);
        self::assertSame('PAID', $this->sell($this->hold(['C-1']), 'FULL', ['method' => 'CASH'])['status']);
    }

    public function testCancelsCounterSalesAndHandsBackPendingRefunds(): void
    {
        $sale = $this->sell($this->hold(['A-3']), 'FULL', ['method' => 'CASH']);
        $byRef = $this->call('GET', '/api/box-office/orders?reference='.$sale['reference'], $this->counter, 200);
        self::assertSame($sale['id'], $byRef['id']);

        $cancelled = $this->call('POST', '/api/box-office/orders/'.$sale['id'].'/cancel', $this->counter, 200);
        self::assertSame('REFUNDED', $cancelled['status']);
        self::assertSame('AVAILABLE', $this->scalar('SELECT status FROM performance_seat WHERE id = ?', $this->seats['A-3']));

        $online = Orders::paid(self::getContainer(), $this->performance, self::createUser('user_alice'), ['B-3']);
        self::assertSame('ONLINE_ORDER', $this->call('POST', '/api/box-office/orders/'.$online->id().'/cancel', $this->counter, 422)['code']);

        // A cancelled performance leaves cash sales waiting at the counter.
        $cash = $this->sell($this->hold(['A-4']), 'REDUCED', ['method' => 'CASH', 'buyerName' => 'Youssef']);
        $this->performance->cancel('Panne technique', $this->clock->now());
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::getContainer()->get(RefundCancelledPerformanceHandler::class)(new PerformanceCancelled($this->performance->id()->toRfc4122(), null, 'Panne technique'));

        $pending = $this->call('GET', '/api/box-office/refunds', $this->counter, 200);
        self::assertSame([$cash['reference']], array_column($pending, 'reference'));
        self::assertSame(SalesFactory::REDUCED, $pending[0]['amountCents']);
        self::assertSame('Youssef', $pending[0]['buyerName']);

        $this->call('POST', '/api/box-office/refunds/'.$pending[0]['id'].'/settle', $this->counter, 200);
        self::assertSame([], $this->call('GET', '/api/box-office/refunds', $this->counter, 200));
        self::assertSame('REFUNDED', $this->scalar('SELECT status FROM customer_order WHERE id = ?', $cash['id']));
    }
}
