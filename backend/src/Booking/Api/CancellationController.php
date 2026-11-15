<?php

declare(strict_types=1);

namespace App\Booking\Api;

use App\Booking\Application\CancellationService;
use App\Booking\Application\Dto\OrderView;
use App\Identity\Domain\Model\User;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Booking')]
#[IsGranted('ROLE_CUSTOMER')]
final readonly class CancellationController
{
    public function __construct(private CancellationService $cancellations)
    {
    }

    #[Route('/api/orders/{id}/cancel', name: 'api_order_cancel', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Order cancelled, places back on sale, refund issued (REFUNDED once the money is back)', content: new Model(type: OrderView::class))]
    #[OA\Response(response: 409, description: 'ORDER_NOT_CANCELLABLE, PERFORMANCE_CANCELLED, ALREADY_CHECKED_IN')]
    #[OA\Response(response: 422, description: 'CANCELLATION_TOO_LATE (less than 48 h before), COUNTER_ORDER')]
    public function cancel(string $id, #[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse($this->cancellations->cancelByCustomer($id, $user->id()));
    }
}
