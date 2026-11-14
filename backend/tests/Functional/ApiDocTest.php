<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiDocTest extends WebTestCase
{
    public function testOpenApiDocumentIsServed(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();
        $doc = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($doc);
        self::assertArrayHasKey('/api/health', $doc['paths']);
    }

    public function testCorsPreflightAllowsLocalFrontend(): void
    {
        $client = self::createClient();
        $client->request('OPTIONS', '/api/health', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
