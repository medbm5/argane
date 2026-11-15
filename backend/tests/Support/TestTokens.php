<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Firebase\JWT\JWT;
use OpenSSLAsymmetricKey;

/**
 * Signs Clerk-like session tokens with a throwaway RSA key and exposes the matching JWKS.
 */
final class TestTokens
{
    public const string ISSUER = 'https://test.clerk.accounts.dev';
    public const string KID = 'test-key-1';
    public const string AZP = 'http://localhost:5173';
    public const int NOW = 1_794_744_000; // 2026-11-15T12:00:00Z

    /** @var array<string, OpenSSLAsymmetricKey> */
    private static array $keys = [];

    public static function privateKey(string $kid = self::KID): OpenSSLAsymmetricKey
    {
        if (!isset(self::$keys[$kid])) {
            // Throwaway keys committed for tests only (key generation needs openssl.cnf on Windows).
            $key = openssl_pkey_get_private((string) file_get_contents(__DIR__.'/keys/'.$kid.'.pem'));
            \assert($key instanceof OpenSSLAsymmetricKey);
            self::$keys[$kid] = $key;
        }

        return self::$keys[$kid];
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    public static function jwks(string ...$kids): array
    {
        $kids = [] === $kids ? [self::KID] : $kids;
        $keys = [];
        foreach ($kids as $kid) {
            $details = openssl_pkey_get_details(self::privateKey($kid));
            \assert(\is_array($details));
            $keys[] = [
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $kid,
                'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
                'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
            ];
        }

        return ['keys' => $keys];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function sign(string $sub = 'user_test', array $overrides = [], string $kid = self::KID): string
    {
        $claims = [
            'sub' => $sub,
            'iss' => self::ISSUER,
            'azp' => self::AZP,
            'sid' => 'sess_123',
            'iat' => self::NOW - 10,
            'nbf' => self::NOW - 10,
            'exp' => self::NOW + 60,
            ...$overrides,
        ];

        return JWT::encode(array_filter($claims, static fn ($v): bool => null !== $v), self::privateKey($kid), 'RS256', $kid);
    }
}
