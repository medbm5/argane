<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Catalog\Application\PerformancePublisher;
use App\Catalog\Domain\Model\Performance;
use App\Pricing\Domain\Model\PerformancePrice;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\SalesChannel;
use App\Shared\Domain\VenueTime;
use App\Tests\Support\CatalogFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class PublicCatalogTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        Clock::set(new MockClock(CatalogFactory::NOW));
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @return array<mixed>
     */
    private function getJson(string $uri, int $expectedStatus = 200): array
    {
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame($expectedStatus);

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($body);

        return $body;
    }

    /**
     * @return list<string>
     */
    private function listedSlugs(string $query = ''): array
    {
        return array_column($this->getJson('/api/events'.$query), 'slug');
    }

    /**
     * A published event with one published, on-sale performance priced Plein 250 / Réduit 150,
     * plus a Groupe (90) and an Invitation that must not count as "from" prices.
     */
    private function seedOnSale(string $title, string $startsAt = '2026-12-12 19:00:00', bool $publishPerformance = true): Performance
    {
        $venue = CatalogFactory::venue();
        $layout = CatalogFactory::layout($venue, withStanding: false);
        $event = CatalogFactory::event($venue, true, $title);
        $performance = CatalogFactory::performance($event, $layout, $startsAt);
        $both = [SalesChannel::ONLINE, SalesChannel::BOX_OFFICE];
        $tariffs = [
            [new Tariff($venue, 'FULL', 'Plein', $both), 25_000],
            [new Tariff($venue, 'REDUCED', 'Réduit', $both), 15_000],
            [new Tariff($venue, 'GROUP', 'Groupe', $both, minQuantity: 10), 9_000],
            [new Tariff($venue, 'INVITATION', 'Invitation', [SalesChannel::BOX_OFFICE], isFree: true), 0],
        ];
        foreach ([$venue, $layout, $event, $performance] as $entity) {
            $this->em->persist($entity);
        }
        $zone = $layout->zoneByCode('PARTERRE') ?? self::fail();
        foreach ($tariffs as [$tariff, $cents]) {
            $this->em->persist($tariff);
            $this->em->persist(new PerformancePrice($performance, $zone, $tariff, $cents));
        }
        $this->em->flush();

        if ($publishPerformance) {
            self::getContainer()->get(PerformancePublisher::class)->publish($performance, openSales: true);
        }

        return $performance;
    }

    public function testListsPublishedEventsWithTheirNextPerformanceAndFromPrice(): void
    {
        $performance = $this->seedOnSale('Visible Show');

        $events = $this->getJson('/api/events');
        $item = array_values(array_filter($events, static fn (array $e): bool => 'Visible Show' === $e['title']))[0] ?? self::fail();

        self::assertSame(15_000, $item['fromPriceCents'], 'group and invitation tariffs are not "from" prices');
        self::assertSame(1, $item['performanceCount']);
        self::assertSame($performance->id()->toRfc4122(), $item['nextPerformance']['id']);
        self::assertSame('ON_SALE', $item['nextPerformance']['status']);
        self::assertTrue($item['nextPerformance']['isOnSale']);
        self::assertSame(11, $item['nextPerformance']['remaining'], '12 seats minus the blocked one');
        self::assertSame('2026-12-12T19:00:00+00:00', $item['nextPerformance']['startsAt']);
    }

    public function testHidesDraftEventsAndUnpublishedPerformances(): void
    {
        $venue = CatalogFactory::venue();
        $draft = CatalogFactory::event($venue, published: false, title: 'Draft Show');
        $this->em->persist($venue);
        $this->em->persist($draft);
        $this->em->flush();
        $this->seedOnSale('Unpublished Performance', publishPerformance: false);

        $slugs = $this->listedSlugs();
        self::assertNotContains($draft->slug(), $slugs);
        foreach ($slugs as $slug) {
            self::assertStringNotContainsString('unpublished-performance', $slug);
        }
        $this->getJson('/api/events/'.$draft->slug(), 404);
    }

    public function testCancelledAndPastPerformancesLeaveTheProgrammeButStayOnTheEventPage(): void
    {
        $cancelled = $this->seedOnSale('Cancelled Show');
        $cancelled->cancel('Artiste souffrant', new DateTimeImmutable(CatalogFactory::NOW));
        $this->em->flush();

        $slug = $cancelled->event()->slug();
        self::assertNotContains($slug, $this->listedSlugs());

        $detail = $this->getJson('/api/events/'.$slug);
        self::assertSame('CANCELLED', $detail['performances'][0]['status']);
        self::assertFalse($detail['performances'][0]['isOnSale']);
        self::assertNull($detail['fromPriceCents']);

        $performance = $this->getJson('/api/performances/'.$cancelled->id()->toRfc4122());
        self::assertSame('CANCELLED', $performance['status']);
        self::assertSame('Artiste souffrant', $performance['cancelReason']);
    }

    public function testFiltersByCategoryAndVenueMonth(): void
    {
        // 00:30 on January 1st at the venue: with a positive UTC offset this instant is
        // still December 31st in UTC, and it must be filed under the venue's month.
        $newYear = VenueTime::fromVenueLocal('2027-01-01 00:30')->format('Y-m-d H:i:s');
        $december = $this->seedOnSale('December Show', $newYear);
        $january = $this->seedOnSale('January Show', '2027-01-10 19:00:00');

        self::assertContains($december->event()->slug(), $this->listedSlugs('?month=2027-01'));
        self::assertContains($january->event()->slug(), $this->listedSlugs('?month=2027-01'));
        self::assertNotContains($january->event()->slug(), $this->listedSlugs('?month=2026-12'));
        self::assertContains($january->event()->slug(), $this->listedSlugs('?category=theatre'));
        self::assertNotContains($january->event()->slug(), $this->listedSlugs('?category=DANCE'));
    }

    public function testRejectsInvalidFilters(): void
    {
        self::assertSame('INVALID_FILTER', $this->getJson('/api/events?category=OPERA', 400)['code']);
        self::assertSame('INVALID_FILTER', $this->getJson('/api/events?month=2026-13', 400)['code']);
    }

    public function testUnknownPerformanceIs404(): void
    {
        self::assertSame('NOT_FOUND', $this->getJson('/api/performances/01a1dce4-0000-7000-8000-000000000000', 404)['code']);
    }

    public function testExposesTheVenue(): void
    {
        $venue = CatalogFactory::venue('aaa');
        $this->em->persist($venue);
        $this->em->persist(CatalogFactory::layout($venue));
        $this->em->flush();

        $body = $this->getJson('/api/venue');
        self::assertSame('Chaque place raconte une histoire.', $body['tagline']);
        self::assertArrayHasKey('layouts', $body);
    }
}
