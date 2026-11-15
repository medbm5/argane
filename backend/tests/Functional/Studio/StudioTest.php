<?php

declare(strict_types=1);

namespace App\Tests\Functional\Studio;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Shared\Domain\VenueTime;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\Orders;
use App\Tests\Support\SalesFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class StudioTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private User $organizer;

    protected function setUp(): void
    {
        Clock::set(new MockClock(CatalogFactory::NOW));
        $this->client = self::createClient();
        self::stubClerk($this->client);
        $this->organizer = self::createUser('user_boss', Role::ORGANIZER);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function call(string $method, string $uri, array $body = [], int $status = 200, ?User $as = null): array
    {
        $this->client->jsonRequest($method, $uri, $body, self::bearer($as ?? $this->organizer));
        self::assertResponseStatusCodeSame($status, (string) $this->client->getResponse()->getContent());

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        return ['name' => 'Cabaret', 'zones' => [
            ['code' => 'SALLE', 'name' => 'Salle', 'kind' => 'SEATED', 'x' => 150, 'y' => 110, 'width' => 700, 'height' => 300, 'colorToken' => 'bordeaux-700', 'rowFrom' => 'A', 'rowTo' => 'D', 'seatsPerRow' => 10, 'aisleAfter' => 5, 'blockedSeats' => ['D-10']],
            ['code' => 'BAR', 'name' => 'Bar debout', 'kind' => 'STANDING', 'x' => 150, 'y' => 450, 'width' => 700, 'height' => 200, 'capacity' => 80],
        ]];
    }

    public function testAnOrganiserTakesAShowFromDraftToSale(): void
    {
        SalesFactory::onSale(self::getContainer()); // a venue with tariffs

        $preview = $this->call('POST', '/api/studio/layouts/preview', $this->spec());
        self::assertNull($preview['id'], 'preview is not saved');
        self::assertSame(39 + 80, $preview['capacity']);
        self::assertCount(40, $preview['seats']);

        $layout = $this->call('POST', '/api/studio/layouts', $this->spec(), 201);
        $event = $this->call('POST', '/api/studio/events', ['title' => 'Nuit du conte', 'category' => 'THEATRE', 'description' => 'Des histoires.', 'durationMinutes' => 75], 201);
        self::assertSame('DRAFT', $event['status']);
        self::assertSame('nuit-du-conte', $event['slug']);

        $event = $this->call('POST', '/api/studio/events/'.$event['id'].'/performances', [
            'startsAt' => '2026-12-20T20:30', 'salesOpenAt' => '2026-11-15T09:00', 'layoutId' => $layout['id'],
        ], 201);
        $performance = $event['performances'][0];
        self::assertFalse($performance['published']);
        self::assertSame(119, $performance['capacity']);

        $grid = $this->call('GET', '/api/studio/performances/'.$performance['id'].'/prices');
        $full = array_values(array_filter($grid['tariffs'], static fn (array $t): bool => 'FULL' === $t['code']))[0]['id'];
        $cells = array_map(static fn (array $z): array => ['zoneId' => $z['id'], 'tariffId' => $full, 'amountCents' => 'BAR' === $z['code'] ? 8000 : 12000], $grid['zones']);
        self::assertCount(2, $this->call('PUT', '/api/studio/performances/'.$performance['id'].'/prices', ['cells' => $cells])['cells']);
        self::assertSame('INVALID_PRICE', $this->call('PUT', '/api/studio/performances/'.$performance['id'].'/prices', ['cells' => [['zoneId' => $cells[0]['zoneId'], 'tariffId' => $full, 'amountCents' => -5]]], 422)['code']);

        self::assertSame('PUBLISHED', $this->call('POST', '/api/studio/events/'.$event['id'].'/publish')['status']);
        $event = $this->call('POST', '/api/studio/performances/'.$performance['id'].'/publish', ['openSales' => true]);
        self::assertSame('ON_SALE', $event['performances'][0]['status']);

        $public = $this->call('GET', '/api/events/nuit-du-conte');
        self::assertSame(8000, $public['fromPriceCents']);
        $expected = VenueTime::fromVenueLocal('2026-12-20 20:30')->format(\DATE_ATOM);
        self::assertSame($expected, $public['performances'][0]['startsAt'], 'venue-local 20:30 stored in UTC');

        $this->call('POST', '/api/studio/performances/'.$performance['id'].'/close-sales');
        self::assertSame('SALES_CLOSED', $this->call('GET', '/api/studio/events/'.$event['id'])['performances'][0]['status']);
    }

    public function testRejectsBadLayoutsAndNonOrganisers(): void
    {
        SalesFactory::onSale(self::getContainer());
        $spec = $this->spec();
        $spec['zones'][0]['width'] = 2000;

        self::assertSame('LAYOUT_ZONE_OUT_OF_BOUNDS', $this->call('POST', '/api/studio/layouts/preview', $spec, 422)['code']);
        $this->call('GET', '/api/studio/events', status: 403, as: self::createUser('user_counter', Role::BOX_OFFICE));
    }

    public function testManagesTariffsAndCopiesAGridAcrossTheEvent(): void
    {
        $first = SalesFactory::onSale(self::getContainer());
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $second = \App\Catalog\Domain\Model\Performance::schedule($first->event(), $first->layout(), new DateTimeImmutable('2026-12-13 19:00:00'), new DateTimeImmutable('2026-11-01 09:00:00'));
        $em->persist($second);
        $em->flush();

        $senior = $this->call('POST', '/api/studio/tariffs', ['code' => 'SENIOR', 'label' => 'Senior', 'requiresProofAtEntry' => true, 'sortOrder' => 5], 201);
        self::assertSame('TARIFF_CODE_TAKEN', $this->call('POST', '/api/studio/tariffs', ['code' => 'SENIOR', 'label' => 'Encore'], 409)['code']);
        self::assertFalse($this->call('POST', '/api/studio/tariffs/'.$senior['id'].'/deactivate')['active']);
        self::assertContains('SENIOR', array_column($this->call('GET', '/api/studio/tariffs'), 'code'));

        self::assertSame(['updated' => 1], $this->call('POST', '/api/studio/performances/'.$first->id().'/prices/apply-to-event'));
        self::assertCount(8, $this->call('GET', '/api/studio/performances/'.$second->id().'/prices')['cells']);
    }

    public function testListsAndExportsSales(): void
    {
        $performance = SalesFactory::onSale(self::getContainer());
        $order = Orders::paid(self::getContainer(), $performance, self::createUser('user_alice'), ['A-1', 'A-2']);

        $page = $this->call('GET', '/api/studio/sales?channel=ONLINE&q='.substr($order->reference(), -6));
        self::assertSame(1, $page['total']);
        self::assertSame(2, $page['ticketsSold']);
        self::assertSame(2 * SalesFactory::FULL, $page['revenueCents']);
        self::assertSame($order->reference(), $page['rows'][0]['reference']);
        self::assertSame('Test user_alice', $page['rows'][0]['buyer']);

        $this->client->request('GET', '/api/studio/sales.csv?event='.$performance->event()->id(), server: self::bearer($this->organizer));
        self::assertResponseIsSuccessful();
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBFRéférence;", $csv);
        self::assertSame(3, substr_count(trim($csv), "\n") + 1, 'header + one line per ticket');
        self::assertStringContainsString($order->reference().';', $csv);
        self::assertStringContainsString(';250,00;', $csv);
    }
}
