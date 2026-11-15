<?php

declare(strict_types=1);

namespace App\Venue\Api;

use App\Shared\Domain\Exception\NotFoundException;
use App\Venue\Api\Dto\VenueResponse;
use App\Venue\Domain\VenueRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Venue')]
final readonly class VenueController
{
    public function __construct(
        private VenueRepository $venues,
        #[Autowire('%app.currency%')]
        private string $currency,
    ) {
    }

    #[Route('/api/venue', name: 'api_venue', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Brand information for the landing page', content: new Model(type: VenueResponse::class))]
    public function __invoke(): JsonResponse
    {
        $venue = $this->venues->main() ?? throw NotFoundException::of('Salle', 'main');

        $response = new JsonResponse(VenueResponse::from($venue, $this->venues->layoutsOf($venue), strtoupper($this->currency)));
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }
}
