<?php

declare(strict_types=1);

namespace App\Catalog\Api;

use App\Catalog\Application\PerformanceCancellation;
use App\Identity\Domain\Model\User;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Organiser actions on a performance (more arrive with the Studio). */
#[AsController]
#[OA\Tag(name: 'Studio')]
#[IsGranted('ROLE_ORGANIZER')]
final readonly class PerformanceAdminController
{
    public function __construct(
        private PerformanceCancellation $cancellation,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/api/studio/performances/{id}/cancel', name: 'api_studio_performance_cancel', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string')]))]
    #[OA\Response(response: 200, description: 'Sales stopped; refunds run in the background')]
    public function cancel(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $body = $request->toArray();
        $performance = $this->cancellation->cancel($id, \is_string($body['reason'] ?? null) ? $body['reason'] : '', $user->id());

        return new JsonResponse([
            'id' => $performance->id()->toRfc4122(),
            'status' => $performance->displayStatus($this->clock->now()),
            'cancelReason' => $performance->cancelReason(),
            'refunds' => 'queued',
        ]);
    }
}
