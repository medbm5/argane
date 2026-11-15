<?php

declare(strict_types=1);

namespace App\Catalog\Api;

use App\Catalog\Application\Query\View\AvailabilityView;
use App\Catalog\Application\Query\View\SeatMapView;
use App\Catalog\Application\SeatMapService;
use App\Identity\Domain\Model\User;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\SalesChannel;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

#[AsController]
#[OA\Tag(name: 'Catalog')]
final readonly class SeatMapController
{
    public function __construct(
        private SeatMapService $seatMaps,
        private Security $security,
        private RoleHierarchyInterface $roleHierarchy,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/api/performances/{id}/seatmap', name: 'api_performance_seatmap', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[OA\Parameter(name: 'channel', in: 'query', description: 'BOX_OFFICE requires the box office role', schema: new OA\Schema(type: 'string', enum: ['ONLINE', 'BOX_OFFICE'], default: 'ONLINE'))]
    #[OA\Response(response: 200, description: 'Plan geometry and the prices the caller may buy', content: new Model(type: SeatMapView::class))]
    #[OA\Response(response: 403, description: 'Box office channel without the role')]
    #[OA\Response(response: 404, description: 'Unknown or unpublished performance')]
    public function seatMap(string $id, #[MapQueryParameter] string $channel = 'ONLINE'): JsonResponse
    {
        $salesChannel = SalesChannel::tryFrom(strtoupper($channel))
            ?? throw BusinessRuleException::because('INVALID_CHANNEL', 'Canal de vente inconnu.', ['channel' => $channel], 400);
        if (SalesChannel::BOX_OFFICE === $salesChannel && !$this->security->isGranted('ROLE_BOX_OFFICE')) {
            throw BusinessRuleException::because('CHANNEL_FORBIDDEN', 'Le guichet est réservé au personnel.', [], 403);
        }

        $response = new JsonResponse($this->seatMaps->seatMap($id, $salesChannel, $this->roles()));
        // Prices depend on the caller's roles: never share this between users.
        $response->setPrivate();
        $response->setMaxAge(60);

        return $response;
    }

    #[Route('/api/performances/{id}/availability', name: 'api_performance_availability', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Seats that are not available, plus standing stock. Polled; supports If-None-Match.', content: new Model(type: AvailabilityView::class))]
    #[OA\Response(response: 304, description: 'Unchanged since the given ETag')]
    #[OA\Response(response: 404, description: 'Unknown or unpublished performance')]
    public function availability(string $id, Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $view = $this->seatMaps->availability($id, $this->clock->now(), $user instanceof User ? $user->id() : null);

        $response = new JsonResponse($view);
        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');
        $response->isNotModified($request);

        return $response;
    }

    /**
     * @return list<string>
     */
    private function roles(): array
    {
        $token = $this->security->getToken();

        return null === $token ? [] : array_values($this->roleHierarchy->getReachableRoleNames($token->getRoleNames()));
    }
}
