<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration;

use App\Booking\Application\CancellationService;
use App\Identity\Domain\Model\Role;
use App\Integration\Application\SheetsClient;
use App\Integration\Application\SheetsSync;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\VenueTime;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\FakeSheetsClient;
use App\Tests\Support\Orders;
use App\Tests\Support\SalesFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class SheetsSyncTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private MockClock $clock;
    private FakeSheetsClient $sheets;
    private SheetsSync $sync;

    protected function setUp(): void
    {
        $this->clock = new MockClock(CatalogFactory::NOW);
        Clock::set($this->clock);
        $this->client = self::createClient();
        self::stubClerk($this->client);
        $sheets = self::getContainer()->get(SheetsClient::class);
        self::assertInstanceOf(FakeSheetsClient::class, $sheets, 'tests never call Google');
        $this->sheets = $sheets;
        $this->sync = self::getContainer()->get(SheetsSync::class);
        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(AdapterInterface::class, $cache);
        $cache->clear();
    }

    public function testMapsOneRowPerTicketAndOneLinePerPerformance(): void
    {
        $performance = SalesFactory::onSale(self::getContainer());
        $order = Orders::paid(self::getContainer(), $performance, self::createUser('user_alice'), ['A-1'], standing: 1, tariffCode: Tariff::REDUCED);

        self::assertTrue($this->sync->sync());

        $sales = $this->sheets->tabs['Ventes'];
        self::assertSame(['Référence', 'Commandé le', 'Spectacle', 'Représentation', 'Zone', 'Place', 'Tarif', 'Canal', 'Prix', 'Statut billet', 'Statut commande'], $sales[0]);
        self::assertCount(3, $sales);
        $local = static fn (string $utc): string => VenueTime::toVenue(new DateTimeImmutable($utc.' UTC'))->format('d/m/Y H:i');
        self::assertContains([$order->reference(), $local('2026-11-15 10:00'), 'Le Misanthrope', $local('2026-12-12 19:00'), 'Parterre', 'A-1', 'Réduit', 'en ligne', 150.0, 'valide', 'payée'], $sales);
        self::assertSame('debout', array_values(array_filter($sales, static fn (array $r): bool => 'Fosse' === $r[4]))[0][5]);

        $summary = array_values(array_filter($this->sheets->tabs['Synthèse'], static fn (array $r): bool => 'Le Misanthrope' === $r[0]));
        self::assertSame(['Le Misanthrope', $local('2026-12-12 19:00'), 'en vente', 2, 61, 0.0328, 300.0, 0], $summary[0]);
        self::assertSame(2, $this->sync->status()['rows']);
    }

    public function testDebouncesBurstsAndKeepsTheSheetInStepWithRefunds(): void
    {
        $performance = SalesFactory::onSale(self::getContainer());
        $alice = self::createUser('user_alice');
        $order = Orders::paid(self::getContainer(), $performance, $alice, ['A-1']);
        $changedAt = (float) $this->clock->now()->format('U.u');

        $this->clock->sleep(15);
        self::assertTrue($this->sync->sync($changedAt));
        self::assertFalse($this->sync->sync($changedAt), 'a later message for the same burst is skipped');
        self::assertSame(2, $this->sheets->writes);

        self::getContainer()->get(CancellationService::class)->cancelByCustomer($order->id()->toRfc4122(), $alice->id());
        $this->clock->sleep(15);
        self::assertTrue($this->sync->sync((float) $this->clock->now()->modify('-15 seconds')->format('U.u')));
        self::assertSame('remboursé', $this->sheets->tabs['Ventes'][1][9]);
        self::assertSame('remboursée', $this->sheets->tabs['Ventes'][1][10]);
    }

    public function testStatusEndpointAndSyncNow(): void
    {
        $organizer = self::createUser('user_boss', Role::ORGANIZER);

        $this->client->request('POST', '/api/studio/integrations/sheets/sync', server: self::bearer($organizer));
        self::assertResponseIsSuccessful();
        $status = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($status);
        self::assertTrue($status['configured']);
        self::assertSame('2026-11-15T10:00:00+00:00', $status['lastSyncAt']);

        $this->sheets->down = true;
        $this->client->request('POST', '/api/studio/integrations/sheets/sync', server: self::bearer($organizer));
        self::assertResponseStatusCodeSame(502);
        $this->client->request('GET', '/api/studio/integrations/sheets', server: self::bearer($organizer));
        self::assertSame('Google is unreachable', json_decode((string) $this->client->getResponse()->getContent(), true)['lastError']);

        $this->sheets->configured = false;
        $this->client->request('POST', '/api/studio/integrations/sheets/sync', server: self::bearer($organizer));
        self::assertResponseStatusCodeSame(409);

        $this->client->request('GET', '/api/studio/integrations/sheets', server: self::bearer(self::createUser('user_counter', Role::BOX_OFFICE)));
        self::assertResponseStatusCodeSame(403);
    }
}
