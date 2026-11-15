<?php

declare(strict_types=1);

namespace App\Catalog\Api;

use App\Catalog\Application\Query\CatalogQueries;
use App\Catalog\Application\Query\View\EventDetailView;
use App\Catalog\Application\Query\View\EventListItemView;
use App\Catalog\Domain\Model\EventCategory;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\NotFoundException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Catalog')]
final readonly class PublicCatalogController
{
    public function __construct(
        private CatalogQueries $catalog,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/api/events', name: 'api_events', methods: ['GET'])]
    #[OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string', enum: ['CONCERT', 'THEATRE', 'CONFERENCE', 'DANCE', 'COMEDY']))]
    #[OA\Parameter(name: 'month', in: 'query', description: 'YYYY-MM, venue time zone', schema: new OA\Schema(type: 'string', pattern: '^\d{4}-\d{2}$'))]
    #[OA\Response(response: 200, description: 'Published events with an upcoming performance', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: EventListItemView::class))))]
    public function list(
        #[MapQueryParameter]
        ?string $category = null,
        #[MapQueryParameter]
        ?string $month = null,
    ): JsonResponse {
        $parsedCategory = null;
        if (null !== $category && '' !== $category) {
            $parsedCategory = EventCategory::tryFrom(strtoupper($category))
                ?? throw BusinessRuleException::because('INVALID_FILTER', 'Catégorie inconnue.', ['category' => $category], 400);
        }
        if (null !== $month && '' !== $month && 1 !== preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw BusinessRuleException::because('INVALID_FILTER', 'Le mois doit être au format AAAA-MM.', ['month' => $month], 400);
        }

        return self::cached(new JsonResponse($this->catalog->listEvents($this->clock->now(), $parsedCategory, '' === $month ? null : $month)));
    }

    #[Route('/api/events/{slug}', name: 'api_event', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Event with its performances', content: new Model(type: EventDetailView::class))]
    #[OA\Response(response: 404, description: 'Unknown or unpublished event')]
    public function show(string $slug): JsonResponse
    {
        $event = $this->catalog->eventBySlug($slug, $this->clock->now())
            ?? throw NotFoundException::of('Spectacle', $slug);

        return self::cached(new JsonResponse($event));
    }

    private static function cached(JsonResponse $response): JsonResponse
    {
        // Availability moves: short shared caching only.
        $response->setPublic();
        $response->setMaxAge(15);

        return $response;
    }
}
