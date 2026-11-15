<?php

declare(strict_types=1);

namespace App\Venue\Api;

use App\Venue\Application\Dto\LayoutSpecInput;
use App\Venue\Application\Dto\LayoutView;
use App\Venue\Application\LayoutStudio;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Studio')]
#[IsGranted('ROLE_ORGANIZER')]
final readonly class StudioLayoutController
{
    public function __construct(private LayoutStudio $studio)
    {
    }

    #[Route('/api/studio/layouts', name: 'api_studio_layouts', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'All configurations (archived included), without seats', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: LayoutView::class))))]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->studio->list());
    }

    #[Route('/api/studio/layouts/{id}', name: 'api_studio_layout', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One configuration with its seats', content: new Model(type: LayoutView::class))]
    public function show(string $id): JsonResponse
    {
        return new JsonResponse($this->studio->view($id));
    }

    #[Route('/api/studio/layouts/preview', name: 'api_studio_layout_preview', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: LayoutSpecInput::class))]
    #[OA\Response(response: 200, description: 'The generated plan, not saved', content: new Model(type: LayoutView::class))]
    public function preview(#[MapRequestPayload] LayoutSpecInput $input): JsonResponse
    {
        return new JsonResponse($this->studio->preview($input));
    }

    #[Route('/api/studio/layouts', name: 'api_studio_layout_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: LayoutSpecInput::class))]
    #[OA\Response(response: 201, description: 'Saved configuration', content: new Model(type: LayoutView::class))]
    public function create(#[MapRequestPayload] LayoutSpecInput $input): JsonResponse
    {
        return new JsonResponse($this->studio->create($input), Response::HTTP_CREATED);
    }

    #[Route('/api/studio/layouts/{id}/clone', name: 'api_studio_layout_clone', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[OA\Response(response: 201, description: 'An editable copy (locked layouts are cloned to be changed)', content: new Model(type: LayoutView::class))]
    public function clone(string $id, Request $request): JsonResponse
    {
        $name = '' === $request->getContent() ? null : ($request->toArray()['name'] ?? null);

        return new JsonResponse($this->studio->clone($id, \is_string($name) ? $name : null), Response::HTTP_CREATED);
    }

    #[Route('/api/studio/layouts/{id}/archive', name: 'api_studio_layout_archive', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Hidden from scheduling')]
    public function archive(string $id): Response
    {
        $this->studio->archive($id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
