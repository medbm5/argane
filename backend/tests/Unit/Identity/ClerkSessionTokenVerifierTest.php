<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Application\InvalidSessionToken;
use App\Identity\Infrastructure\Clerk\ClerkSessionTokenVerifier;
use App\Identity\Infrastructure\Clerk\JwksProvider;
use App\Tests\Support\TestTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ClerkSessionTokenVerifierTest extends TestCase
{
    private int $jwksRequests = 0;

    /**
     * @param list<array{keys: list<array<string, mixed>>}> $jwksResponses served in order
     */
    private function verifier(array $jwksResponses = [], int $now = TestTokens::NOW): ClerkSessionTokenVerifier
    {
        $jwksResponses = [] === $jwksResponses ? [TestTokens::jwks()] : $jwksResponses;
        $http = new MockHttpClient(function () use (&$jwksResponses): JsonMockResponse {
            ++$this->jwksRequests;

            return new JsonMockResponse(array_shift($jwksResponses) ?? TestTokens::jwks());
        });

        return new ClerkSessionTokenVerifier(
            new JwksProvider($http, new ArrayAdapter(), 'https://test.clerk.accounts.dev/.well-known/jwks.json'),
            new MockClock('@'.$now),
            TestTokens::ISSUER,
            'http://localhost:5173, https://argane.vercel.app/',
        );
    }

    public function testAcceptsAValidToken(): void
    {
        $session = $this->verifier()->verify(TestTokens::sign('user_abc'));

        self::assertSame('user_abc', $session->clerkUserId);
        self::assertSame('sess_123', $session->sessionId);
        self::assertSame(TestTokens::AZP, $session->authorizedParty);
        self::assertSame(TestTokens::NOW + 60, $session->expiresAt->getTimestamp());
    }

    public function testCachesTheKeySet(): void
    {
        $verifier = $this->verifier();
        $verifier->verify(TestTokens::sign());
        $verifier->verify(TestTokens::sign());

        self::assertSame(1, $this->jwksRequests);
    }

    public function testRefreshesTheKeySetOnceForAnUnknownKid(): void
    {
        $verifier = $this->verifier([TestTokens::jwks(), TestTokens::jwks(TestTokens::KID, 'rotated')]);

        $session = $verifier->verify(TestTokens::sign('user_rotated', [], 'rotated'));

        self::assertSame('user_rotated', $session->clerkUserId);
        self::assertSame(2, $this->jwksRequests);
    }

    public function testToleratesSmallClockSkew(): void
    {
        $token = TestTokens::sign(overrides: ['exp' => TestTokens::NOW - 3]);

        self::assertSame('user_test', $this->verifier()->verify($token)->clerkUserId);
    }

    public function testAcceptsTokensWithoutAzp(): void
    {
        $token = TestTokens::sign(overrides: ['azp' => null]);

        self::assertNull($this->verifier()->verify($token)->authorizedParty);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTokens(): iterable
    {
        yield 'expired' => [TestTokens::sign(overrides: ['exp' => TestTokens::NOW - 30])];
        yield 'not yet valid' => [TestTokens::sign(overrides: ['nbf' => TestTokens::NOW + 30, 'iat' => TestTokens::NOW + 30])];
        yield 'wrong issuer' => [TestTokens::sign(overrides: ['iss' => 'https://evil.clerk.accounts.dev'])];
        yield 'unauthorized party' => [TestTokens::sign(overrides: ['azp' => 'https://evil.example'])];
        yield 'missing subject' => [TestTokens::sign(overrides: ['sub' => null])];
        yield 'signed by an unknown key' => [TestTokens::sign(kid: 'attacker')];
        yield 'malformed' => ['not-a-jwt'];
        yield 'garbage header' => ['%%%.e30.sig'];
    }

    #[DataProvider('invalidTokens')]
    public function testRejectsInvalidTokens(string $token): void
    {
        $this->expectException(InvalidSessionToken::class);

        $this->verifier()->verify($token);
    }

    public function testRejectsATamperedPayload(): void
    {
        [$header, , $signature] = explode('.', TestTokens::sign('user_a'));
        $forged = $header.'.'.rtrim(strtr(base64_encode((string) json_encode([
            'sub' => 'user_admin', 'iss' => TestTokens::ISSUER, 'exp' => TestTokens::NOW + 60,
        ])), '+/', '-_'), '=').'.'.$signature;

        $this->expectException(InvalidSessionToken::class);

        $this->verifier()->verify($forged);
    }
}
