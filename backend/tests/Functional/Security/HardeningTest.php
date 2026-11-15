<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Identity\Domain\Model\Role;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\SalesFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class HardeningTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        Clock::set(new MockClock(CatalogFactory::NOW));
        $this->client = self::createClient();
        self::stubClerk($this->client);
    }

    public function testEveryApiResponseCarriesSecurityHeaders(): void
    {
        $this->client->request('GET', '/api/events');

        $headers = $this->client->getResponse()->headers;
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('no-referrer', $headers->get('Referrer-Policy'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertStringContainsString("default-src 'none'", (string) $headers->get('Content-Security-Policy'));
        self::assertNull($headers->get('Strict-Transport-Security'), 'HSTS only over HTTPS');

        $this->client->request('GET', '/api/events', server: ['HTTPS' => 'on']);
        self::assertStringContainsString('max-age=31536000', (string) $this->client->getResponse()->headers->get('Strict-Transport-Security'));
    }

    public function testCorsAnswersTheFrontendOriginOnly(): void
    {
        $preflight = ['HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST', 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization, content-type'];

        $this->client->request('OPTIONS', '/api/events', server: ['HTTP_ORIGIN' => 'http://localhost:5173', ...$preflight]);
        self::assertSame('http://localhost:5173', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
        self::assertStringNotContainsString('PATCH', (string) $this->client->getResponse()->headers->get('Access-Control-Allow-Methods'));

        $this->client->request('OPTIONS', '/api/events', server: ['HTTP_ORIGIN' => 'https://evil.example', ...$preflight]);
        self::assertNull($this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testCheckoutIsRateLimitedPerBuyer(): void
    {
        $alice = self::createUser('user_alice');
        for ($i = 0; $i < 10; ++$i) {
            $this->client->jsonRequest('POST', '/api/holds/'.Uuid::v7().'/checkout', [], self::bearer($alice));
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->jsonRequest('POST', '/api/holds/'.Uuid::v7().'/checkout', [], self::bearer($alice));
        self::assertResponseStatusCodeSame(429);
        self::assertSame('TOO_MANY_CHECKOUTS', json_decode((string) $this->client->getResponse()->getContent(), true)['code']);
    }

    public function testCheckInIsRateLimitedPerSteward(): void
    {
        $performance = SalesFactory::onSale(self::getContainer());
        $steward = self::createUser('user_steward', Role::BOX_OFFICE);
        $uri = '/api/check-in/performances/'.$performance->id().'/scan';
        for ($i = 0; $i < 5; ++$i) {
            $this->client->jsonRequest('POST', $uri, ['qr' => 'nope'], self::bearer($steward));
            self::assertResponseIsSuccessful();
        }
        $this->client->jsonRequest('POST', $uri, ['qr' => 'nope'], self::bearer($steward));
        self::assertResponseStatusCodeSame(429);
    }
}
