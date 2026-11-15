<?php

declare(strict_types=1);

namespace App\Ticketing\Api;

use App\Identity\Domain\Model\User;
use App\Shared\Application\RateLimit;
use App\Ticketing\Application\CheckInService;
use App\Ticketing\Application\Dto\CheckInResult;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Check-in')]
#[IsGranted('ROLE_BOX_OFFICE')]
final readonly class CheckInController
{
    public function __construct(
        private CheckInService $checkIn,
        #[Autowire(service: 'limiter.check_in')]
        private RateLimiterFactoryInterface $checkInLimiter,
    ) {
    }

    #[Route('/api/check-in/performances/{id}/scan', name: 'api_check_in_scan', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(required: ['qr'], properties: [new OA\Property(property: 'qr', type: 'string')]))]
    #[OA\Response(response: 200, description: 'A verdict for the scanner (admitted, already used, wrong date, not valid, invalid)', content: new Model(type: CheckInResult::class))]
    public function scan(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        RateLimit::consume($this->checkInLimiter, $user->id()->toRfc4122(), 'TOO_MANY_SCANS', 'Trop de scans d’affilée. Patientez quelques secondes.');
        $qr = $request->toArray()['qr'] ?? '';

        return new JsonResponse($this->checkIn->scan(\is_string($qr) ? $qr : '', $id, $user->id()));
    }

    #[Route('/api/check-in/performances/{id}/counts', name: 'api_check_in_counts', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Admitted so far / tickets expected', content: new OA\JsonContent(properties: [new OA\Property(property: 'admitted', type: 'integer'), new OA\Property(property: 'expected', type: 'integer')]))]
    public function counts(string $id): JsonResponse
    {
        return new JsonResponse($this->checkIn->counts($id));
    }
}
