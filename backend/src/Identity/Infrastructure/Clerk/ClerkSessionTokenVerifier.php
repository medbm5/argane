<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Clerk;

use App\Identity\Application\Dto\VerifiedSession;
use App\Identity\Application\InvalidSessionToken;
use App\Identity\Application\SessionTokenVerifier;
use DateTimeImmutable;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Psr\Clock\ClockInterface;
use stdClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Verifies Clerk session JWTs (RS256) networklessly against the cached JWKS:
 * signature, `exp`/`nbf` (with 5 s leeway), issuer and authorized party (`azp`).
 *
 * @see https://clerk.com/docs/backend-requests/manual-jwt
 */
final readonly class ClerkSessionTokenVerifier implements SessionTokenVerifier
{
    private const int LEEWAY_SECONDS = 5;

    /** @var list<string> */
    private array $authorizedParties;

    public function __construct(
        private JwksProvider $jwks,
        private ClockInterface $clock,
        #[Autowire('%env(CLERK_ISSUER)%')]
        private string $issuer,
        #[Autowire('%env(CLERK_AUTHORIZED_PARTIES)%')]
        string $authorizedParties,
    ) {
        $this->authorizedParties = array_values(array_filter(array_map(
            static fn (string $p): string => rtrim(trim($p), '/'),
            explode(',', $authorizedParties),
        )));
    }

    public function verify(string $token): VerifiedSession
    {
        $kid = $this->kidOf($token);
        $keySet = $this->jwks->keySet();
        if (!$this->hasKid($keySet, $kid)) {
            $keySet = $this->jwks->keySet(refresh: true);
        }

        $previousLeeway = JWT::$leeway;
        $previousTimestamp = JWT::$timestamp;
        JWT::$leeway = self::LEEWAY_SECONDS;
        JWT::$timestamp = $this->clock->now()->getTimestamp();
        try {
            $claims = JWT::decode($token, JWK::parseKeySet($keySet, 'RS256'));
        } catch (Throwable $e) {
            throw InvalidSessionToken::because($e->getMessage(), $e);
        } finally {
            JWT::$leeway = $previousLeeway;
            JWT::$timestamp = $previousTimestamp;
        }

        return $this->checkClaims($claims);
    }

    private function checkClaims(stdClass $claims): VerifiedSession
    {
        $sub = $claims->sub ?? null;
        if (!\is_string($sub) || '' === $sub) {
            throw InvalidSessionToken::because('Missing subject.');
        }

        $iss = $claims->iss ?? null;
        if (!\is_string($iss) || rtrim($iss, '/') !== rtrim($this->issuer, '/')) {
            throw InvalidSessionToken::because('Unexpected issuer.');
        }

        $exp = $claims->exp ?? null;
        if (!\is_int($exp)) {
            throw InvalidSessionToken::because('Missing expiry.');
        }

        $azp = isset($claims->azp) && \is_string($claims->azp) ? rtrim($claims->azp, '/') : null;
        if (null !== $azp && [] !== $this->authorizedParties && !\in_array($azp, $this->authorizedParties, true)) {
            throw InvalidSessionToken::because('Unauthorized party.');
        }

        $sid = isset($claims->sid) && \is_string($claims->sid) ? $claims->sid : null;

        return new VerifiedSession($sub, $sid, $azp, new DateTimeImmutable('@'.$exp));
    }

    private function kidOf(string $token): ?string
    {
        $parts = explode('.', $token);
        if (3 !== \count($parts)) {
            throw InvalidSessionToken::because('Malformed token.');
        }
        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
        } catch (Throwable $e) {
            throw InvalidSessionToken::because('Malformed token header.', $e);
        }

        return $header instanceof stdClass && isset($header->kid) && \is_string($header->kid) ? $header->kid : null;
    }

    /**
     * @param array{keys: list<array<string, mixed>>} $keySet
     */
    private function hasKid(array $keySet, ?string $kid): bool
    {
        foreach ($keySet['keys'] as $key) {
            if (($key['kid'] ?? null) === $kid) {
                return true;
            }
        }

        return false;
    }
}
