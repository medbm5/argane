<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Application\ClerkDirectory;
use App\Identity\Application\Dto\ClerkProfile;
use App\Identity\Infrastructure\Clerk\JwksProvider;
use App\Tests\Support\TestTokens;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class MeTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        Clock::set(new MockClock('@'.TestTokens::NOW));
        $this->client = self::createClient();
        $container = self::getContainer();

        $container->set(JwksProvider::class, new class extends JwksProvider {
            public function __construct()
            {
            }

            public function keySet(bool $refresh = false): array
            {
                return TestTokens::jwks();
            }
        });
        $container->set(ClerkDirectory::class, new class implements ClerkDirectory {
            public function fetchProfile(string $clerkUserId): ClerkProfile
            {
                return new ClerkProfile($clerkUserId, $clerkUserId.'@argane.test', 'Test '.$clerkUserId);
            }
        });
    }

    public function testRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testRejectsAnInvalidToken(): void
    {
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer nope']);

        self::assertResponseStatusCodeSame(401);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertSame('UNAUTHENTICATED', $body['code']);
    }

    public function testProvisionsAndReturnsTheCurrentUser(): void
    {
        $this->client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.TestTokens::sign('user_me'),
        ]);

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($body);
        self::assertSame('user_me@argane.test', $body['email']);
        self::assertSame(['ROLE_CUSTOMER'], $body['roles']);
        self::assertFalse($body['isOrganizer']);
    }
}
