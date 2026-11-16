<?php

declare(strict_types=1);

namespace App\Catalog\Api;

use App\Catalog\Application\PosterLibrary;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\NotFoundException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Studio')]
final readonly class PosterController
{
    public function __construct(private PosterLibrary $posters)
    {
    }

    #[Route('/api/studio/posters', name: 'api_studio_poster_upload', methods: ['POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    #[OA\RequestBody(content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['file'], properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')])))]
    #[OA\Response(response: 201, description: 'Stored; use `path` as the event poster path', content: new OA\JsonContent(required: ['path'], properties: [new OA\Property(property: 'path', type: 'string')]))]
    #[OA\Response(response: 422, description: 'POSTER_INVALID or POSTER_TOO_LARGE')]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw BusinessRuleException::because('POSTER_INVALID', 'Choisissez une image JPEG, PNG ou WebP.');
        }

        return new JsonResponse(['path' => $this->posters->upload($file)->path()], Response::HTTP_CREATED);
    }

    #[Route('/api/posters/{id}', name: 'api_poster', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[OA\Tag(name: 'Catalog')]
    #[OA\Response(response: 200, description: 'The image, cached for a year (posters never change; a new upload gets a new id)')]
    public function show(string $id): Response
    {
        $poster = $this->posters->find($id) ?? throw NotFoundException::of('Affiche', $id);

        return new Response($poster['content'], Response::HTTP_OK, [
            'Content-Type' => $poster['mimeType'],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            // The site (another origin) shows it in an <img>.
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }
}
