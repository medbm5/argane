<?php

declare(strict_types=1);

namespace App\Integration\Api;

use App\Integration\Application\SheetsSync;
use App\Shared\Domain\Exception\BusinessRuleException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[AsController]
#[OA\Tag(name: 'Studio')]
#[IsGranted('ROLE_ORGANIZER')]
final readonly class IntegrationController
{
    public function __construct(private SheetsSync $sync)
    {
    }

    #[Route('/api/studio/integrations/sheets', name: 'api_integration_sheets', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'configured, url, lastSyncAt, lastError, rows')]
    public function status(): JsonResponse
    {
        return new JsonResponse($this->sync->status());
    }

    #[Route('/api/studio/integrations/sheets/sync', name: 'api_integration_sheets_sync', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Synced now; the new status')]
    #[OA\Response(response: 409, description: 'SHEETS_NOT_CONFIGURED')]
    #[OA\Response(response: 502, description: 'SHEETS_SYNC_FAILED (Google refused or is unreachable)')]
    public function syncNow(): JsonResponse
    {
        if (!$this->sync->status()['configured']) {
            throw BusinessRuleException::because('SHEETS_NOT_CONFIGURED', 'Google Sheets n’est pas configuré sur ce serveur.', [], 409);
        }
        try {
            $this->sync->sync();
        } catch (Throwable $e) {
            throw BusinessRuleException::because('SHEETS_SYNC_FAILED', 'La synchronisation a échoué : '.$e->getMessage(), [], 502);
        }

        return new JsonResponse($this->sync->status());
    }
}
