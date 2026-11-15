<?php

declare(strict_types=1);

namespace App\Integration\Infrastructure;

use App\Integration\Application\SheetsClient;
use Firebase\JWT\JWT;
use LogicException;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Google Sheets over plain REST with a service account: a signed JWT is exchanged for
 * an access token (cached for its lifetime), then values are cleared and written.
 * No Google SDK: two endpoints are all we need.
 *
 * Configuration: GOOGLE_SHEETS_SPREADSHEET_ID and GOOGLE_SERVICE_ACCOUNT_JSON_BASE64
 * (the downloaded key file, base64-encoded). The sheet must be shared with the
 * service account's email as an editor.
 */
final class GoogleSheetsClient implements SheetsClient
{
    private const string SCOPE = 'https://www.googleapis.com/auth/spreadsheets';
    private const string API = 'https://sheets.googleapis.com/v4/spreadsheets/';

    private ?string $token = null;
    private int $tokenExpiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly ClockInterface $clock,
        #[Autowire('%env(default::GOOGLE_SHEETS_SPREADSHEET_ID)%')]
        private readonly ?string $spreadsheetId,
        #[Autowire('%env(default::GOOGLE_SERVICE_ACCOUNT_JSON_BASE64)%')]
        private readonly ?string $serviceAccount,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== (string) $this->spreadsheetId && '' !== (string) $this->serviceAccount;
    }

    public function url(): ?string
    {
        return $this->isConfigured() ? 'https://docs.google.com/spreadsheets/d/'.$this->spreadsheetId : null;
    }

    public function replaceTab(string $tab, array $rows): void
    {
        $this->ensureTab($tab);
        $range = rawurlencode("'".str_replace("'", "''", $tab)."'");
        $this->call('POST', $this->spreadsheetId.'/values/'.$range.':clear', []);
        $this->call('PUT', $this->spreadsheetId.'/values/'.$range.'!A1?valueInputOption=RAW', ['range' => "'{$tab}'!A1", 'majorDimension' => 'ROWS', 'values' => $rows]);
    }

    private function ensureTab(string $tab): void
    {
        $sheet = $this->call('GET', $this->spreadsheetId.'?fields=sheets.properties.title');
        $titles = array_map(static fn (array $s): string => (string) ($s['properties']['title'] ?? ''), (array) ($sheet['sheets'] ?? []));
        if (!\in_array($tab, $titles, true)) {
            $this->call('POST', $this->spreadsheetId.':batchUpdate', ['requests' => [['addSheet' => ['properties' => ['title' => $tab]]]]]);
        }
    }

    /**
     * @param array<string, mixed>|null $json
     *
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, ?array $json = null): array
    {
        $response = $this->http->request($method, self::API.$path, array_filter([
            'auth_bearer' => $this->token(),
            'json' => $json,
            'timeout' => 20,
        ], static fn (mixed $v): bool => null !== $v));

        /** @var array<string, mixed> */
        return $response->toArray();
    }

    private function token(): string
    {
        $now = $this->clock->now()->getTimestamp();
        if (null !== $this->token && $now < $this->tokenExpiresAt - 60) {
            return $this->token;
        }
        $key = json_decode((string) base64_decode((string) $this->serviceAccount, true), true);
        if (!\is_array($key) || !\is_string($key['client_email'] ?? null) || !\is_string($key['private_key'] ?? null)) {
            throw new LogicException('GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 is not a service account key.');
        }
        $tokenUri = \is_string($key['token_uri'] ?? null) ? $key['token_uri'] : 'https://oauth2.googleapis.com/token';
        $assertion = JWT::encode([
            'iss' => $key['client_email'],
            'scope' => self::SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $key['private_key'], 'RS256');

        $data = $this->http->request('POST', $tokenUri, [
            'body' => ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion],
            'timeout' => 15,
        ])->toArray();
        $this->token = (string) $data['access_token'];
        $this->tokenExpiresAt = $now + (int) ($data['expires_in'] ?? 3600);

        return $this->token;
    }
}
