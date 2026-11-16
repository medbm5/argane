<?php

declare(strict_types=1);

namespace App\Catalog\Api;

use App\Catalog\Application\Dto\SeatBlockingInput;
use App\Catalog\Application\HallSeats;
use App\Venue\Application\Dto\LayoutView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Studio')]
#[IsGranted('ROLE_ORGANIZER')]
final readonly class StudioHallController
{
    public function __construct(private HallSeats $hall)
    {
    }

    #[Route('/api/studio/layouts/{id}/seats', name: 'api_studio_layout_seats', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['PUT'])]
    #[OA\RequestBody(content: new Model(type: SeatBlockingInput::class))]
    #[OA\Response(
        response: 200,
        description: 'Plan updated; upcoming dates follow where the seats are free',
        content: new OA\JsonContent(
            required: ['layout', 'seatsUpdated', 'seatsKept'],
            properties: [
                new OA\Property(property: 'layout', ref: new Model(type: LayoutView::class)),
                new OA\Property(property: 'seatsUpdated', description: 'Seats changed across upcoming dates', type: 'integer'),
                new OA\Property(property: 'seatsKept', description: 'Seats left as they were because sold or being paid for', type: 'integer'),
            ],
        ),
    )]
    #[OA\Response(response: 422, description: 'SEAT_NOT_IN_LAYOUT or NO_SEAT_SELECTED')]
    public function setBlocked(string $id, #[MapRequestPayload] SeatBlockingInput $input): JsonResponse
    {
        return new JsonResponse($this->hall->setBlocked($id, $input->seatIds, $input->blocked));
    }
}
