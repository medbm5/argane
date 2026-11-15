<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration;

use App\Integration\Infrastructure\GoogleSheetsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleSheetsClientTest extends TestCase
{
    /** @var list<array{method: string, url: string, body: string}> */
    private array $calls = [];

    private function client(): GoogleSheetsClient
    {
        $key = base64_encode((string) json_encode([
            'type' => 'service_account',
            'client_email' => 'sync@argane-test.iam.gserviceaccount.com',
            'private_key' => (string) file_get_contents(__DIR__.'/../../Support/keys/test-key-1.pem'),
            'token_uri' => 'https://oauth2.example.test/token',
        ]));
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->calls[] = ['method' => $method, 'url' => $url, 'body' => (string) ($options['body'] ?? '')];

            return match (true) {
                str_contains($url, 'oauth2') => new MockResponse('{"access_token":"tok","expires_in":3600}'),
                str_contains($url, 'fields=sheets') => new MockResponse('{"sheets":[{"properties":{"title":"Feuille 1"}}]}'),
                default => new MockResponse('{}'),
            };
        });

        return new GoogleSheetsClient($http, new MockClock(), 'sheet-1', $key);
    }

    public function testCreatesTheTabThenClearsAndRewritesIt(): void
    {
        $this->client()->replaceTab('Ventes', [['Référence', 'Montant'], ['AR-1', 90]]);

        $sheets = array_values(array_filter($this->calls, static fn (array $c): bool => !str_contains($c['url'], 'oauth2')));
        self::assertSame(['GET', 'POST', 'POST', 'PUT'], array_column($sheets, 'method'));
        self::assertStringEndsWith(':batchUpdate', $sheets[1]['url']);
        self::assertStringContainsString('"addSheet"', $sheets[1]['body']);
        self::assertStringEndsWith('/values/%27Ventes%27:clear', $sheets[2]['url']);
        // Google rejects a JSON array here ("Root element must be a message").
        self::assertSame('{}', $sheets[2]['body']);
        self::assertSame([['Référence', 'Montant'], ['AR-1', 90]], json_decode($sheets[3]['body'], true)['values'] ?? null);
    }
}
