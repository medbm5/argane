<?php

declare(strict_types=1);

namespace App\Catalog\Api;

use App\Catalog\Application\Dto\EventInput;
use App\Catalog\Application\Dto\PerformanceInput;
use App\Catalog\Application\EventStudio;
use App\Catalog\Application\Query\View\StudioEventView;
use App\Identity\Domain\Model\User;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Studio')]
#[IsGranted('ROLE_ORGANIZER')]
final readonly class StudioEventController
{
    private const string UUID = '[0-9a-fA-F-]{36}';

    public function __construct(private EventStudio $studio)
    {
    }

    #[Route('/api/studio/events', name: 'api_studio_events', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Every event, newest first, with sales totals', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: StudioEventView::class))))]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->studio->list());
    }

    #[Route('/api/studio/events', name: 'api_studio_event_create', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: EventInput::class))]
    #[OA\Response(response: 201, description: 'Draft event', content: new Model(type: StudioEventView::class))]
    public function create(#[MapRequestPayload] EventInput $input, #[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse($this->studio->create($input, $user->id()), Response::HTTP_CREATED);
    }

    #[Route('/api/studio/events/{id}', name: 'api_studio_event', requirements: ['id' => self::UUID], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Event with all its performances', content: new Model(type: StudioEventView::class))]
    public function show(string $id): JsonResponse
    {
        return new JsonResponse($this->studio->view($id));
    }

    #[Route('/api/studio/events/{id}', name: 'api_studio_event_update', requirements: ['id' => self::UUID], methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: EventInput::class))]
    #[OA\Response(response: 200, description: 'Updated event', content: new Model(type: StudioEventView::class))]
    public function update(string $id, #[MapRequestPayload] EventInput $input): JsonResponse
    {
        return new JsonResponse($this->studio->update($id, $input));
    }

    #[Route('/api/studio/events/{id}/publish', name: 'api_studio_event_publish', requirements: ['id' => self::UUID], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Event visible in the programme', content: new Model(type: StudioEventView::class))]
    public function publish(string $id): JsonResponse
    {
        return new JsonResponse($this->studio->publish($id));
    }

    #[Route('/api/studio/events/{id}/archive', name: 'api_studio_event_archive', requirements: ['id' => self::UUID], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Event archived', content: new Model(type: StudioEventView::class))]
    public function archive(string $id): JsonResponse
    {
        return new JsonResponse($this->studio->archive($id));
    }

    #[Route('/api/studio/events/{id}/performances', name: 'api_studio_performance_schedule', requirements: ['id' => self::UUID], methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: PerformanceInput::class))]
    #[OA\Response(response: 201, description: 'Performance scheduled (unpublished)', content: new Model(type: StudioEventView::class))]
    public function schedule(string $id, #[MapRequestPayload] PerformanceInput $input): JsonResponse
    {
        return new JsonResponse($this->studio->schedule($id, $input), Response::HTTP_CREATED);
    }

    #[Route('/api/studio/performances/{id}', name: 'api_studio_performance_update', requirements: ['id' => self::UUID], methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: PerformanceInput::class))]
    #[OA\Response(response: 200, description: 'Rescheduled (layout changes only before publication)', content: new Model(type: StudioEventView::class))]
    public function reschedule(string $id, #[MapRequestPayload] PerformanceInput $input): JsonResponse
    {
        return new JsonResponse($this->studio->reschedule($id, $input));
    }

    #[Route('/api/studio/performances/{id}/publish', name: 'api_studio_performance_publish', requirements: ['id' => self::UUID], methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: 'openSales', type: 'boolean')]))]
    #[OA\Response(response: 200, description: 'Published, inventory generated', content: new Model(type: StudioEventView::class))]
    public function publishPerformance(string $id, Request $request): JsonResponse
    {
        $open = '' !== $request->getContent() && true === ($request->toArray()['openSales'] ?? false);

        return new JsonResponse($this->studio->publishPerformance($id, $open));
    }

    #[Route('/api/studio/performances/{id}/open-sales', name: 'api_studio_performance_open', requirements: ['id' => self::UUID], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'On sale', content: new Model(type: StudioEventView::class))]
    public function openSales(string $id): JsonResponse
    {
        return new JsonResponse($this->studio->openSales($id));
    }

    #[Route('/api/studio/performances/{id}/close-sales', name: 'api_studio_performance_close', requirements: ['id' => self::UUID], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Online and counter sales closed', content: new Model(type: StudioEventView::class))]
    public function closeSales(string $id): JsonResponse
    {
        return new JsonResponse($this->studio->closeSales($id));
    }
}
