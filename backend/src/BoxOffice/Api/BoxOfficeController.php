<?php

declare(strict_types=1);

namespace App\BoxOffice\Api;

use App\Booking\Application\Dto\OrderView;
use App\BoxOffice\Application\BoxOfficeService;
use App\BoxOffice\Application\Dto\CounterPerformanceView;
use App\BoxOffice\Application\Dto\CounterSaleRequest;
use App\BoxOffice\Application\Dto\PendingRefundView;
use App\Identity\Domain\Model\User;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Box office')]
#[IsGranted('ROLE_BOX_OFFICE')]
final readonly class BoxOfficeController
{
    public function __construct(
        private BoxOfficeService $boxOffice,
        private RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    #[Route('/api/box-office/performances', name: 'api_box_office_performances', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Today and the next 14 days, soonest first', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: CounterPerformanceView::class))))]
    public function performances(): JsonResponse
    {
        return new JsonResponse($this->boxOffice->performances());
    }

    #[Route('/api/box-office/holds/{id}/sell', name: 'api_box_office_sell', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: CounterSaleRequest::class))]
    #[OA\Response(response: 201, description: 'Paid counter order (tickets valid, places sold)', content: new Model(type: OrderView::class))]
    public function sell(string $id, #[CurrentUser] User $user, #[MapRequestPayload] CounterSaleRequest $request): JsonResponse
    {
        $roles = array_values($this->roleHierarchy->getReachableRoleNames($user->getRoles()));

        return new JsonResponse($this->boxOffice->sell($id, $user->id(), $roles, $request), Response::HTTP_CREATED);
    }

    #[Route('/api/box-office/orders', name: 'api_box_office_order_lookup', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Order found by reference (ARG-26-000123)', content: new Model(type: OrderView::class))]
    public function lookup(#[MapQueryParameter] string $reference): JsonResponse
    {
        return new JsonResponse($this->boxOffice->findByReference($reference));
    }

    #[Route('/api/box-office/orders/{id}/cancel', name: 'api_box_office_cancel', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Counter sale cancelled, money handed back, places back on sale', content: new Model(type: OrderView::class))]
    public function cancel(string $id, #[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse($this->boxOffice->cancel($id, $user->id()));
    }

    #[Route('/api/box-office/refunds', name: 'api_box_office_refunds', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Refunds to hand back at the counter, oldest first', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: PendingRefundView::class))))]
    public function refunds(): JsonResponse
    {
        return new JsonResponse($this->boxOffice->pendingRefunds());
    }

    #[Route('/api/box-office/refunds/{id}/settle', name: 'api_box_office_refund_settle', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Money handed back; the order is REFUNDED', content: new Model(type: PendingRefundView::class))]
    public function settle(string $id): JsonResponse
    {
        return new JsonResponse($this->boxOffice->settleRefund($id));
    }
}
