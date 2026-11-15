<?php

declare(strict_types=1);

namespace App\Tests\Functional\Booking;

use App\Booking\Application\HoldService;
use App\Booking\Domain\HoldRepository;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\User;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\SalesFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class HoldTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private MockClock $clock;
    private Performance $performance;
    /** @var array<string, string> */
    private array $seats;

    protected function setUp(): void
    {
        $this->clock = new MockClock(CatalogFactory::NOW);
        Clock::set($this->clock);
        $this->client = self::createClient();
        self::stubClerk($this->client);
        $this->performance = SalesFactory::onSale(self::getContainer());
        $this->seats = SalesFactory::seatIds(self::getContainer()->get(Connection::class), $this->performance);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function hold(User $user, array $body, int $status = 201): array
    {
        $this->client->jsonRequest('POST', '/api/performances/'.$this->performance->id().'/holds', $body, self::bearer($user));
        self::assertResponseStatusCodeSame($status, (string) $this->client->getResponse()->getContent());

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    /**
     * @return array<mixed>
     */
    private function availability(?User $user = null): array
    {
        $this->client->request('GET', '/api/performances/'.$this->performance->id().'/availability', server: null === $user ? [] : self::bearer($user));

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    public function testRequiresASignedInBuyer(): void
    {
        $this->client->jsonRequest('POST', '/api/performances/'.$this->performance->id().'/holds', ['seatIds' => [$this->seats['A-1']]]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testHoldsSeatsForTenMinutesAndShowsThemAsMine(): void
    {
        $alice = self::createUser('user_alice');
        $hold = $this->hold($alice, ['seatIds' => [$this->seats['A-1'], $this->seats['A-2']]]);

        self::assertSame('ACTIVE', $hold['status']);
        self::assertSame(600, $hold['secondsLeft']);
        self::assertEqualsCanonicalizing([$this->seats['A-1'], $this->seats['A-2']], $hold['seatIds']);

        self::assertEqualsCanonicalizing($hold['seatIds'], $this->availability($alice)['mine']);
        self::assertEqualsCanonicalizing($hold['seatIds'], $this->availability()['held'], 'others see them held');
    }

    public function testASeatHeldBySomeoneElseIsAConflictAndNothingIsHeld(): void
    {
        $this->hold(self::createUser('user_alice'), ['seatIds' => [$this->seats['A-1']]]);

        $conflict = $this->hold(self::createUser('user_bob'), ['seatIds' => [$this->seats['A-2'], $this->seats['A-1']]], 409);

        self::assertSame('SEATS_UNAVAILABLE', $conflict['code']);
        self::assertSame([$this->seats['A-1']], $conflict['seatIds']);
        self::assertNotContains($this->seats['A-2'], $this->availability()['held'], 'the free seat was rolled back');
    }

    public function testBlockedAndUnknownSeatsCannotBeHeld(): void
    {
        $conflict = $this->hold(self::createUser('user_alice'), ['seatIds' => [$this->seats['C-4'], Uuid::v7()->toRfc4122()]], 409);

        self::assertCount(2, $conflict['seatIds']);
    }

    public function testANewHoldReplacesTheBuyersPreviousOneOnThisPerformance(): void
    {
        $alice = self::createUser('user_alice');
        $first = $this->hold($alice, ['seatIds' => [$this->seats['A-1']]]);
        $second = $this->hold($alice, ['seatIds' => [$this->seats['B-1']]]);

        $this->client->request('GET', '/api/holds/'.$first['id'], server: self::bearer($alice));
        self::assertSame('RELEASED', json_decode((string) $this->client->getResponse()->getContent(), true)['status']);
        self::assertSame([$this->seats['B-1']], $this->availability($alice)['mine']);
        self::assertSame([$this->seats['B-1']], $this->availability()['held'], 'A-1 is free again');
        self::assertNotSame($first['id'], $second['id']);
    }

    public function testReleasingGivesThePlacesBackAndOnlyTheOwnerMayDoIt(): void
    {
        $alice = self::createUser('user_alice');
        $hold = $this->hold($alice, ['seatIds' => [$this->seats['A-1']], 'standing' => [['zoneId' => SalesFactory::zoneId($this->performance, 'FOSSE'), 'quantity' => 2]]]);

        $this->client->request('DELETE', '/api/holds/'.$hold['id'], server: self::bearer(self::createUser('user_mallory')));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('DELETE', '/api/holds/'.$hold['id'], server: self::bearer($alice));
        self::assertResponseStatusCodeSame(204);
        $this->client->request('DELETE', '/api/holds/'.$hold['id'], server: self::bearer($alice));
        self::assertResponseStatusCodeSame(204, 'idempotent');

        $availability = $this->availability();
        self::assertSame([], $availability['held']);
        self::assertSame(50, $availability['standing'][0]['remaining']);
    }

    public function testStandingCapacityIsNeverExceeded(): void
    {
        $fosse = SalesFactory::zoneId($this->performance, 'FOSSE');
        $holders = [];
        foreach (range(1, 5) as $i) {
            $holders[] = $this->hold(self::createUser('user_'.$i), ['standing' => [['zoneId' => $fosse, 'quantity' => 10]]]);
        }

        $conflict = $this->hold(self::createUser('user_late'), ['standing' => [['zoneId' => $fosse, 'quantity' => 1]]], 409);
        self::assertSame('STANDING_UNAVAILABLE', $conflict['code']);
        self::assertSame(0, $conflict['remaining']);
        self::assertSame(0, $this->availability()['standing'][0]['remaining']);
        self::assertCount(5, $holders);
    }

    public function testExpiredHoldsAreAvailableEverywhereAndGetTidiedUp(): void
    {
        $alice = self::createUser('user_alice');
        $fosse = SalesFactory::zoneId($this->performance, 'FOSSE');
        $hold = $this->hold($alice, ['seatIds' => [$this->seats['A-1']], 'standing' => [['zoneId' => $fosse, 'quantity' => 9]]]);

        $this->clock->sleep(601);

        $availability = $this->availability();
        self::assertSame([], $availability['held'], 'lazy expiry in reads');
        self::assertSame(50, $availability['standing'][0]['remaining']);

        $bob = self::createUser('user_bob');
        $this->hold($bob, ['seatIds' => [$this->seats['A-1']], 'standing' => [['zoneId' => $fosse, 'quantity' => 9]]]);

        self::assertGreaterThanOrEqual(1, self::getContainer()->get(HoldService::class)->expireDue());
        $expired = self::getContainer()->get(HoldRepository::class)->find(Uuid::fromString($hold['id']));
        self::getContainer()->get('doctrine')->getManager()->refresh($expired ?? self::fail());
        self::assertSame('EXPIRED', $expired->status()->value);
        self::assertSame([$this->seats['A-1']], $this->availability($bob)['mine'], 'housekeeping never frees a live hold');
    }

    public function testRejectsEmptyOversizedAndOffSaleHolds(): void
    {
        $alice = self::createUser('user_alice');
        self::assertSame('EMPTY_HOLD', $this->hold($alice, ['seatIds' => []], 422)['code']);

        $tooMany = $this->hold($alice, ['standing' => [['zoneId' => SalesFactory::zoneId($this->performance, 'FOSSE'), 'quantity' => 11]]], 422);
        self::assertSame('TOO_MANY_PLACES', $tooMany['code']);

        self::assertSame('UNKNOWN_ZONE', $this->hold($alice, ['standing' => [['zoneId' => SalesFactory::zoneId($this->performance, 'PARTERRE'), 'quantity' => 1]]], 422)['code']);

        $this->clock->modify('2026-12-12 19:30:00');
        self::assertSame('NOT_ON_SALE', $this->hold($alice, ['seatIds' => [$this->seats['A-1']]], 422)['code']);
    }

    public function testBoxOfficeChannelNeedsTheRole(): void
    {
        $this->hold(self::createUser('user_alice'), ['seatIds' => [$this->seats['A-1']], 'channel' => 'BOX_OFFICE'], 403);
    }

    public function testRateLimitsHoldCreationPerUser(): void
    {
        $alice = self::createUser('user_spammer');
        for ($i = 0; $i < 20; ++$i) {
            $this->hold($alice, ['seatIds' => [$this->seats[0 === $i % 2 ? 'A-1' : 'A-2']]]);
        }

        self::assertSame('TOO_MANY_HOLDS', $this->hold($alice, ['seatIds' => [$this->seats['A-3']]], 429)['code']);
    }
}
