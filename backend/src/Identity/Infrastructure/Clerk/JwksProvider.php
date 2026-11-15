<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Clerk;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches Clerk's JSON Web Key Set and caches it for an hour.
 * When a token carries an unknown `kid` (key rotation), the cache is refreshed once.
 */
class JwksProvider
{
    private const string CACHE_KEY = 'clerk_jwks';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        #[Autowire('%env(CLERK_JWKS_URL)%')]
        private readonly string $jwksUrl,
    ) {
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    public function keySet(bool $refresh = false): array
    {
        if ($refresh) {
            $this->cache->delete(self::CACHE_KEY);
        }

        /** @var array{keys: list<array<string, mixed>>} $jwks */
        $jwks = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(3600);
            $data = $this->httpClient->request('GET', $this->jwksUrl, ['timeout' => 5])->toArray();
            if (!isset($data['keys']) || !\is_array($data['keys'])) {
                throw new RuntimeException('Clerk JWKS response has no "keys".');
            }

            return ['keys' => array_values($data['keys'])];
        });

        return $jwks;
    }
}
