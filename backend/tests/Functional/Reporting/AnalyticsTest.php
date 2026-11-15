<?php

declare(strict_types=1);

namespace App\Tests\Functional\Reporting;

use App\Booking\Application\CancellationService;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Pricing\Domain\Model\Tariff;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\Orders;
use App\Tests\Support\SalesFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * KPI math on a known scenario, sold on 15/11 for the 12/12 show (61 places: 11 seats
 * + 50 standing):
 *   Alice  A-1, A-2  Plein  2 × 250  online
 *   Bob    B-1       Réduit 1 × 150  counter
 *   Carol  A-3       Plein  1 × 250  online, then cancelled and refunded.
 */
final class AnalyticsTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private MockClock $clock;
    private Performance $performance;
    private User $organizer;

    protected function setUp(): void
    {
        $this->clock = new MockClock(CatalogFactory::NOW);
        Clock::set($this->clock);
        $this->client = self::createClient();
        self::stubClerk($this->client);
        $container = self::getContainer();
        $this->performance = SalesFactory::onSale($container);
        Orders::paid($container, $this->performance, self::createUser('user_alice'), ['A-1', 'A-2']);
        $bob = Orders::paid($container, $this->performance, self::createUser('user_bob'), ['B-1'], tariffCode: Tariff::REDUCED);
        $container->get(Connection::class)->executeStatement("UPDATE customer_order SET channel = 'BOX_OFFICE' WHERE id = ?", [$bob->id()->toBinary()], [ParameterType::BINARY]);
        $carol = self::createUser('user_carol');
        $order = Orders::paid($container, $this->performance, $carol, ['A-3']);
        $container->get(CancellationService::class)->cancelByCustomer($order->id()->toRfc4122(), $carol->id());
        $this->organizer = self::createUser('user_boss', Role::ORGANIZER);
    }

    /**
     * @return array<mixed>
     */
    private function get(string $uri): array
    {
        $this->client->request('GET', '/api/studio/analytics/'.$uri, server: self::bearer($this->organizer));
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    public function testOverviewKpisAgainstThePreviousPeriod(): void
    {
        $kpis = $this->get('overview?from=2026-11-15&to=2026-11-15')['kpis'];

        self::assertSame(['value' => 65_000, 'previous' => 0, 'delta' => null], $kpis['revenueCents']);
        self::assertSame(3, $kpis['tickets']['value']);
        self::assertSame(2, $kpis['orders']['value'], 'the refunded order sold nothing');
        self::assertSame(32_500, $kpis['averageBasketCents']['value']);
        self::assertSame(0.6667, $kpis['onlineShare']['value']);
        self::assertSame(1, $kpis['refundedOrders']['value']);

        // Next day: nothing sold, and the deltas compare with the 15th.
        $next = $this->get('overview?from=2026-11-16&to=2026-11-16')['kpis'];
        self::assertEquals(['value' => 0, 'previous' => 65_000, 'delta' => -1.0], $next['revenueCents'], 'JSON turns -1.0 into -1');
    }

    public function testFillPerPerformanceAndZone(): void
    {
        $fill = $this->get('fill')[0];

        self::assertSame(3, $fill['sold']);
        self::assertSame(61, $fill['capacity']);
        self::assertSame([
            ['code' => 'PARTERRE', 'name' => 'Parterre', 'sold' => 3, 'capacity' => 11],
            ['code' => 'FOSSE', 'name' => 'Fosse', 'sold' => 0, 'capacity' => 50],
        ], $fill['zones']);
    }

    public function testRevenueByTariffAndChannelAndPerDay(): void
    {
        $revenue = $this->get('revenue?from=2026-11-01&to=2026-11-30');
        self::assertSame([
            ['tariff' => 'Plein', 'tickets' => 2, 'revenueCents' => 50_000],
            ['tariff' => 'Réduit', 'tickets' => 1, 'revenueCents' => 15_000],
        ], $revenue['byTariff']);
        self::assertSame([
            ['channel' => 'ONLINE', 'tickets' => 2, 'revenueCents' => 50_000],
            ['channel' => 'BOX_OFFICE', 'tickets' => 1, 'revenueCents' => 15_000],
        ], $revenue['byChannel']);

        $days = $this->get('timeseries?from=2026-11-14&to=2026-11-16');
        self::assertSame(['2026-11-14', '2026-11-15', '2026-11-16'], array_column($days, 'date'));
        self::assertSame([0, 3, 0], array_column($days, 'tickets'));
    }

    public function testBookingCurveHeatmapAndAtRisk(): void
    {
        $curve = $this->get('booking-curve?performance='.$this->performance->id())['performance'];
        $byDay = array_column($curve, 'rate', 'daysBefore');
        self::assertSame(0, (int) $byDay[28] * 100, 'nothing sold 28 days before');
        self::assertEqualsWithDelta(3 / 61, $byDay[27], 0.0001);
        self::assertEqualsWithDelta(3 / 61, $byDay[0], 0.0001);

        $heatmap = $this->get('heatmap?layout='.$this->performance->layout()->id());
        $rates = array_column($heatmap['seats'], 'rate', 'label');
        self::assertSame(1, $heatmap['performances']);
        self::assertEquals(1.0, $rates['A-1']);
        self::assertEquals(0.0, $rates['A-3'], 'cancelled sale freed the seat');
        self::assertArrayNotHasKey('C-4', $rates, 'blocked seats are not part of the map');

        $calm = $this->get('at-risk');
        self::assertSame([], $calm, 'four weeks out, no history: no alarm');
        $this->clock->modify('2026-12-08 10:00:00');
        $risk = $this->get('at-risk?late');
        self::assertCount(1, $risk);
        self::assertSame(4, $risk[0]['daysLeft']);
    }

    public function testOrganisersOnlyAndRangeValidation(): void
    {
        $this->client->request('GET', '/api/studio/analytics/overview', server: self::bearer(self::createUser('user_counter', Role::BOX_OFFICE)));
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/api/studio/analytics/overview?from=2026-12-01&to=2026-11-01', server: self::bearer($this->organizer));
        self::assertResponseStatusCodeSame(400);
    }
}
