<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ticketing;

use App\Booking\Domain\Model\Order;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\Orders;
use App\Tests\Support\SalesFactory;
use App\Ticketing\Application\QrSigner;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class TicketDownloadTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private User $alice;
    private Order $order;

    protected function setUp(): void
    {
        Clock::set(new MockClock(CatalogFactory::NOW));
        $this->client = self::createClient();
        self::stubClerk($this->client);
        $performance = SalesFactory::onSale(self::getContainer());
        $this->alice = self::createUser('user_alice');
        $this->order = Orders::paid(self::getContainer(), $performance, $this->alice, ['A-1', 'A-2'], standing: 1);
    }

    public function testTheWalletListsPaidOrdersWithSignedQrCodes(): void
    {
        $this->client->request('GET', '/api/me/orders', server: self::bearer($this->alice));
        self::assertResponseIsSuccessful();
        $wallet = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertIsArray($wallet);
        self::assertCount(1, $wallet);
        self::assertSame($this->order->reference(), $wallet[0]['reference']);
        self::assertCount(3, $wallet[0]['tickets']);
        $signer = self::getContainer()->get(QrSigner::class);
        foreach ($this->order->tickets() as $i => $ticket) {
            $qr = $wallet[0]['tickets'][$i]['qr'];
            self::assertTrue($signer->isAuthentic($qr, $ticket));
        }

        $this->client->request('GET', '/api/me/orders', server: self::bearer(self::createUser('user_bob')));
        self::assertSame('[]', $this->client->getResponse()->getContent());
    }

    public function testDownloadsTheOrderAndSingleTicketsAsPdf(): void
    {
        $this->client->request('GET', '/api/orders/'.$this->order->id().'/tickets.pdf', server: self::bearer($this->alice));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('billets-'.$this->order->reference().'.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $ticket = $this->order->tickets()[0];
        $this->client->request('GET', '/api/tickets/'.$ticket->id().'.pdf', server: self::bearer($this->alice));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('billet-'.$this->order->reference().'-A-1.pdf', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testOnlyTheBuyerAndStaffMayPrint(): void
    {
        $uri = '/api/orders/'.$this->order->id().'/tickets.pdf';

        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(401);
        $this->client->request('GET', $uri, server: self::bearer(self::createUser('user_mallory')));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', $uri, server: self::bearer(self::createUser('user_counter', Role::BOX_OFFICE)));
        self::assertResponseIsSuccessful();
    }
}
