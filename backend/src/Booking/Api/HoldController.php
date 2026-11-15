<?php

declare(strict_types=1);

namespace App\Booking\Api;

use App\Booking\Application\Dto\HoldRequest;
use App\Booking\Application\Dto\HoldView;
use App\Booking\Application\HoldService;
use App\Identity\Domain\Model\User;
use App\Shared\Domain\Exception\BusinessRuleException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Booking')]
#[IsGranted('ROLE_CUSTOMER')]
final readonly class HoldController
{
    public function __construct(
        private HoldService $holds,
        private RoleHierarchyInterface $roleHierarchy,
        #[Autowire(service: 'limiter.hold_creation')]
        private RateLimiterFactoryInterface $holdLimiter,
    ) {
    }

    #[Route('/api/performances/{id}/holds', name: 'api_hold_create', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: HoldRequest::class))]
    #[OA\Response(response: 201, description: 'Places held for 10 minutes; replaces the caller\'s previous hold on this performance', content: new Model(type: HoldView::class))]
    #[OA\Response(response: 409, description: 'SEATS_UNAVAILABLE (context.seatIds) or STANDING_UNAVAILABLE')]
    #[OA\Response(response: 422, description: 'EMPTY_HOLD, TOO_MANY_PLACES, NOT_ON_SALE, UNKNOWN_ZONE')]
    #[OA\Response(response: 429, description: 'Too many hold attempts')]
    public function create(string $id, #[CurrentUser] User $user, #[MapRequestPayload] HoldRequest $request): JsonResponse
    {
        $limit = $this->holdLimiter->create($user->id()->toRfc4122())->consume();
        if (!$limit->isAccepted()) {
            throw BusinessRuleException::because('TOO_MANY_HOLDS', 'Trop de tentatives de réservation. Patientez une minute.', ['retryAfter' => $limit->getRetryAfter()->getTimestamp()], 429);
        }

        $roles = array_values($this->roleHierarchy->getReachableRoleNames($user->getRoles()));

        return new JsonResponse($this->holds->hold($id, $user->id(), $roles, $request), Response::HTTP_CREATED);
    }

    #[Route('/api/holds/{id}', name: 'api_hold_show', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The caller\'s hold', content: new Model(type: HoldView::class))]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse($this->holds->view($id, $user->id()));
    }

    #[Route('/api/holds/{id}', name: 'api_hold_release', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Places released (idempotent)')]
    public function release(string $id, #[CurrentUser] User $user): Response
    {
        $this->holds->release($id, $user->id());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
