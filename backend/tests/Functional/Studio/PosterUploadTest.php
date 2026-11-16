<?php

declare(strict_types=1);

namespace App\Tests\Functional\Studio;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\SalesFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PosterUploadTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private User $organizer;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::stubClerk($this->client);
        $this->organizer = self::createUser('user_poster_boss', Role::ORGANIZER);
    }

    /** A real 1×1 PNG. */
    private static function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    }

    private function upload(string $bytes, string $name, ?User $as = null): void
    {
        $path = tempnam(sys_get_temp_dir(), 'poster');
        file_put_contents((string) $path, $bytes);
        $this->client->request(
            'POST',
            '/api/studio/posters',
            files: ['file' => new UploadedFile((string) $path, $name, null, null, true)],
            server: self::bearer($as ?? $this->organizer),
        );
    }

    /** @return array<mixed> */
    private function json(): array
    {
        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    public function testAnOrganizerUploadsAPosterThatAnyoneCanThenSee(): void
    {
        $bytes = self::png();
        $this->upload($bytes, 'affiche.png');

        self::assertResponseStatusCodeSame(201);
        $path = (string) $this->json()['path'];
        self::assertMatchesRegularExpression('#^/api/posters/[0-9a-f-]{36}$#', $path);

        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertSame($bytes, $response->getContent(), 'served as uploaded');
        self::assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('cross-origin', $response->headers->get('Cross-Origin-Resource-Policy'), 'the site shows it from another origin');
    }

    public function testTheUploadedPathIsAcceptedAsAnEventPoster(): void
    {
        SalesFactory::onSale(self::getContainer()); // a venue to create the event in
        $this->upload(self::png(), 'affiche.png');
        $path = (string) $this->json()['path'];

        $this->client->jsonRequest('POST', '/api/studio/events', [
            'title' => 'Affiche maison',
            'category' => 'THEATRE',
            'description' => 'Une pièce.',
            'durationMinutes' => 90,
            'posterPath' => $path,
        ], self::bearer($this->organizer));

        self::assertResponseStatusCodeSame(201);
        self::assertSame($path, $this->json()['posterPath']);
    }

    public function testRejectsWhatIsNotAPhotoPoster(): void
    {
        $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'affiche.svg');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('POSTER_INVALID', $this->json()['code']);

        $this->upload('not an image at all', 'affiche.png');
        self::assertResponseStatusCodeSame(422);

        $this->upload(str_repeat('x', 2 * 1024 * 1024 + 1), 'big.jpg');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('POSTER_TOO_LARGE', $this->json()['code']);
    }

    public function testOnlyTheOrganizerUploads(): void
    {
        $this->upload(self::png(), 'affiche.png', self::createUser('user_poster_fan'));

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnUnknownPosterIs404(): void
    {
        $this->client->request('GET', '/api/posters/01890000-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }
}
