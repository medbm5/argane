<?php

declare(strict_types=1);

namespace App\Catalog\Api;

use App\Catalog\Application\Query\CatalogQueries;
use App\Catalog\Application\Query\View\PerformanceDetailView;
use App\Shared\Domain\Exception\NotFoundException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Catalog')]
final readonly class PerformanceController
{
    public function __construct(
        private CatalogQueries $catalog,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/api/performances/{id}', name: 'api_performance', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Performance detail with remaining places and the lowest single online price', content: new Model(type: PerformanceDetailView::class))]
    #[OA\Response(response: 404, description: 'Unknown or unpublished performance')]
    public function show(string $id): JsonResponse
    {
        $performance = $this->catalog->performance($id, $this->clock->now())
            ?? throw NotFoundException::of('Représentation', $id);

        $response = new JsonResponse($performance);
        $response->setPublic();
        $response->setMaxAge(10);

        return $response;
    }
}
