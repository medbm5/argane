<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Catalog\Application\PerformancePublisher;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\Role;
use App\Pricing\Domain\Model\PerformancePrice;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\SalesChannel;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class SeatMapTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        Clock::set(new MockClock(CatalogFactory::NOW));
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        self::stubClerk($this->client);
    }

    /**
     * Parterre (12 seats, C-4 blocked) and Fosse (50 standing), priced Plein / Réduit /
     * Invitation (box office staff only).
     */
    private function seed(bool $publish = true): Performance
    {
        $venue = CatalogFactory::venue();
        $layout = CatalogFactory::layout($venue);
        $event = CatalogFactory::event($venue);
        $performance = CatalogFactory::performance($event, $layout);
        foreach ([$venue, $layout, $event, $performance] as $entity) {
            $this->em->persist($entity);
        }
        $both = [SalesChannel::ONLINE, SalesChannel::BOX_OFFICE];
        $tariffs = [
            [new Tariff($venue, 'FULL', 'Plein', $both), 25_000],
            [new Tariff($venue, 'REDUCED', 'Réduit', $both, requiresProofAtEntry: true), 15_000],
            [new Tariff($venue, 'INVITATION', 'Invitation', [SalesChannel::BOX_OFFICE], allowedRoles: ['ROLE_BOX_OFFICE'], isFree: true), 0],
        ];
        foreach ($tariffs as [$tariff]) {
            $this->em->persist($tariff);
        }
        foreach ($layout->zones() as $zone) {
            foreach ($tariffs as [$tariff, $cents]) {
                $this->em->persist(new PerformancePrice($performance, $zone, $tariff, $cents));
            }
        }
        $this->em->flush();
        if ($publish) {
            self::getContainer()->get(PerformancePublisher::class)->publish($performance, openSales: true);
        }

        return $performance;
    }

    /**
     * @param array<string, string> $server
     *
     * @return array<mixed>
     */
    private function getJson(string $uri, int $status = 200, array $server = []): array
    {
        $this->client->request('GET', $uri, server: $server);
        self::assertResponseStatusCodeSame($status);

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($body);

        return $body;
    }

    public function testServesGeometryWithOnlinePricesToTheAnonymousPublic(): void
    {
        $performance = $this->seed();
        $map = $this->getJson('/api/performances/'.$performance->id().'/seatmap');

        self::assertSame(['width' => 1000, 'height' => 700], $map['viewBox']);
        self::assertSame('ONLINE', $map['channel']);
        self::assertSame('MAD', $map['currency']);
        self::assertCount(12, $map['seats']);
        self::assertSame(['PARTERRE', 'FOSSE'], array_column($map['zones'], 'code'));

        $parterre = $map['zones'][0];
        self::assertStringStartsWith('M', $parterre['shape']);
        self::assertEqualsCanonicalizing(['FULL', 'REDUCED'], array_column($parterre['prices'], 'tariffCode'), 'invitation is box office only');
        $reduced = $parterre['prices'][array_search('REDUCED', array_column($parterre['prices'], 'tariffCode'), true)];
        self::assertTrue($reduced['requiresProofAtEntry']);
        self::assertSame(50, $map['zones'][1]['capacity']);

        $a1 = array_values(array_filter($map['seats'], static fn (array $s): bool => 'A-1' === $s['label']))[0] ?? self::fail();
        self::assertTrue($a1['accessible']);
        self::assertSame($parterre['id'], $a1['zoneId']);
    }

    public function testBoxOfficeChannelAddsRestrictedTariffsForStaffOnly(): void
    {
        $performance = $this->seed();
        $uri = '/api/performances/'.$performance->id().'/seatmap?channel=BOX_OFFICE';

        $this->getJson($uri, 403);
        $this->getJson($uri, 403, self::bearer(self::createUser('user_customer')));

        $map = $this->getJson($uri, 200, self::bearer(self::createUser('user_counter', Role::BOX_OFFICE)));
        $codes = array_column($map['zones'][0]['prices'], 'tariffCode');
        self::assertContains('INVITATION', $codes);
        $invitation = $map['zones'][0]['prices'][array_search('INVITATION', $codes, true)];
        self::assertTrue($invitation['requiresReason']);
        self::assertSame(0, $invitation['amountCents']);
    }

    public function testHidesUnpublishedPerformances(): void
    {
        $performance = $this->seed(publish: false);

        $this->getJson('/api/performances/'.$performance->id().'/seatmap', 404);
        $this->getJson('/api/performances/'.$performance->id().'/availability', 404);
    }

    public function testListsOnlyUnavailableSeatsAndHonoursHoldExpiry(): void
    {
        $performance = $this->seed();
        $connection = self::getContainer()->get(Connection::class);
        $seatIds = array_column($this->getJson('/api/performances/'.$performance->id().'/seatmap')['seats'], 'id', 'label');

        $mark = static function (string $id, string $status, ?string $expires = null) use ($connection): void {
            $connection->executeStatement(
                'UPDATE performance_seat SET status = ?, hold_expires_at = ? WHERE id = ?',
                [$status, $expires, Uuid::fromString($id)->toBinary()],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::BINARY],
            );
        };
        $mark($seatIds['A-1'], 'SOLD');
        $mark($seatIds['A-2'], 'HELD', '2026-11-15 10:05:00');
        $mark($seatIds['A-3'], 'HELD', '2026-11-15 09:55:00'); // expired: free again

        $availability = $this->getJson('/api/performances/'.$performance->id().'/availability');

        self::assertSame('ON_SALE', $availability['status']);
        self::assertSame([$seatIds['A-1']], $availability['sold']);
        self::assertSame([$seatIds['A-2']], $availability['held']);
        self::assertSame([], $availability['mine']);
        self::assertSame([$seatIds['C-4']], $availability['blocked']);
        self::assertSame(50, $availability['standing'][0]['remaining']);
        self::assertSame(9 + 50, $availability['remaining']);
    }

    public function testAnswersNotModifiedWhileNothingChanges(): void
    {
        $performance = $this->seed();
        $uri = '/api/performances/'.$performance->id().'/availability';

        $this->client->request('GET', $uri);
        $etag = (string) $this->client->getResponse()->headers->get('ETag');
        self::assertNotSame('', $etag);
        self::assertStringContainsString('no-cache', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', $uri, server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);

        Clock::set(new MockClock('2027-01-01 10:00:00')); // the performance is now past
        $this->client->request('GET', $uri, server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(200);
    }
}
