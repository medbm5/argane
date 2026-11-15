<?php

declare(strict_types=1);

namespace App\Identity\Api;

use App\Identity\Api\Dto\MeResponse;
use App\Identity\Domain\Model\User;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Identity')]
final readonly class MeController
{
    public function __construct(private RoleHierarchyInterface $roleHierarchy)
    {
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('ROLE_CUSTOMER')]
    #[OA\Response(response: 200, description: 'The signed-in user and their effective roles', content: new Model(type: MeResponse::class))]
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        $roles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());

        $response = new JsonResponse(MeResponse::from($user, $roles));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
