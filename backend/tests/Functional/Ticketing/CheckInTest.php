<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ticketing;

use App\Booking\Domain\Model\Order;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Pricing\Domain\Model\Tariff;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\Orders;
use App\Tests\Support\SalesFactory;
use App\Ticketing\Application\QrSigner;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class CheckInTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private Performance $performance;
    private User $steward;
    private Order $order;
    private QrSigner $signer;

    protected function setUp(): void
    {
        Clock::set(new MockClock(CatalogFactory::NOW));
        $this->client = self::createClient();
        self::stubClerk($this->client);
        $this->performance = SalesFactory::onSale(self::getContainer());
        $this->steward = self::createUser('user_steward', Role::BOX_OFFICE);
        $this->order = Orders::paid(self::getContainer(), $this->performance, self::createUser('user_alice'), ['A-1', 'A-2'], tariffCode: Tariff::REDUCED);
        $this->signer = self::getContainer()->get(QrSigner::class);
        Clock::set(new MockClock('2026-12-12 18:45:00')); // doors are open
    }

    /**
     * @return array<string, mixed>
     */
    private function scan(string $qr, ?Performance $at = null, ?User $as = null): array
    {
        $this->client->jsonRequest('POST', '/api/check-in/performances/'.($at ?? $this->performance)->id().'/scan', ['qr' => $qr], self::bearer($as ?? $this->steward));
        self::assertResponseIsSuccessful();

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function qr(int $ticket = 0): string
    {
        return $this->signer->sign($this->order->tickets()[$ticket]);
    }

    public function testAdmitsOnceAndRefusesTheSecondScan(): void
    {
        $first = $this->scan($this->qr());
        self::assertSame('ADMITTED', $first['verdict']);
        self::assertSame('A-1', $first['seat']);
        self::assertTrue($first['requiresProof'], 'reduced tariff: ask for proof');
        self::assertStringContainsString('justificatif', (string) $first['message']);
        self::assertSame(1, $first['admitted']);
        self::assertSame(2, $first['expected']);

        $second = $this->scan($this->qr());
        self::assertSame('ALREADY_USED', $second['verdict']);
        self::assertNotNull($second['checkedInAt']);
        self::assertSame(1, $second['admitted']);
    }

    public function testRefusesTicketsForAnotherDate(): void
    {
        $otherDay = SalesFactory::onSale(self::getContainer(), '2026-12-13 19:00:00');

        $result = $this->scan($this->qr(), $otherDay);

        self::assertSame('WRONG_PERFORMANCE', $result['verdict']);
        self::assertSame('2026-12-12T19:00:00+00:00', $result['ticketPerformanceStartsAt']);
        self::assertSame('VALID', $this->order->tickets()[0]->status()->value, 'not consumed');
    }

    public function testRefusesCancelledTamperedAndForeignCodes(): void
    {
        Clock::set(new MockClock(CatalogFactory::NOW)); // cancel well before the show
        $alice = self::getContainer()->get('doctrine')->getManager()->getRepository(User::class)->findOneBy(['clerkUserId' => 'user_alice']) ?? self::fail();
        $this->client->jsonRequest('POST', '/api/orders/'.$this->order->id().'/cancel', [], self::bearer($alice));
        self::assertResponseIsSuccessful();
        Clock::set(new MockClock('2026-12-12 18:45:00'));

        self::assertSame('NOT_VALID', $this->scan($this->qr(1))['verdict']);

        $tampered = substr($this->qr(), 0, -2).'xx';
        self::assertSame('INVALID', $this->scan($tampered)['verdict']);
        self::assertSame('INVALID', $this->scan('https://example.com/ticket/123')['verdict']);
    }

    public function testOnlyStaffMayScan(): void
    {
        $this->client->jsonRequest('POST', '/api/check-in/performances/'.$this->performance->id().'/scan', ['qr' => $this->qr()], self::bearer(self::createUser('user_bob')));
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/api/check-in/performances/'.$this->performance->id().'/counts', server: self::bearer($this->steward));
        self::assertSame(['admitted' => 0, 'expected' => 2], json_decode((string) $this->client->getResponse()->getContent(), true));
    }
}
