<?php

declare(strict_types=1);

namespace App\Booking\Api;

use App\Booking\Application\CheckoutService;
use App\Booking\Application\Dto\CheckoutRequest;
use App\Booking\Application\Dto\CheckoutView;
use App\Booking\Application\Dto\OrderView;
use App\Identity\Domain\Model\User;
use App\Shared\Application\RateLimit;
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
final readonly class CheckoutController
{
    public function __construct(
        private CheckoutService $checkout,
        private RoleHierarchyInterface $roleHierarchy,
        #[Autowire(service: 'limiter.checkout')]
        private RateLimiterFactoryInterface $checkoutLimiter,
    ) {
    }

    #[Route('/api/holds/{id}/checkout', name: 'api_checkout', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: CheckoutRequest::class))]
    #[OA\Response(response: 201, description: 'Order awaiting payment, with the Stripe client secret', content: new Model(type: CheckoutView::class))]
    #[OA\Response(response: 409, description: 'HOLD_EXPIRED')]
    #[OA\Response(response: 422, description: 'CHECKOUT_MISMATCH, TARIFF_NOT_PRICED, TARIFF_MIN_QUANTITY, …')]
    #[OA\Response(response: 429, description: 'TOO_MANY_CHECKOUTS')]
    public function checkout(string $id, #[CurrentUser] User $user, #[MapRequestPayload] CheckoutRequest $request): JsonResponse
    {
        RateLimit::consume($this->checkoutLimiter, $user->id()->toRfc4122(), 'TOO_MANY_CHECKOUTS', 'Trop de tentatives de paiement. Patientez une minute.');
        $roles = array_values($this->roleHierarchy->getReachableRoleNames($user->getRoles()));

        return new JsonResponse($this->checkout->checkout($id, $user->id(), $user->email(), $roles, $request), Response::HTTP_CREATED);
    }

    #[Route('/api/orders/{id}', name: 'api_order_show', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The caller\'s order; reconciles with Stripe while unpaid', content: new Model(type: OrderView::class))]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $response = new JsonResponse($this->checkout->order($id, $user->id()));
        $response->setPrivate();

        return $response;
    }
}
