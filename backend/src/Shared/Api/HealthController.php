<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Liveness + database ping. Used by Render's health check and by the
 * frontend cold-start loader while the free instance wakes up.
 */
#[AsController]
final readonly class HealthController
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $database = 'ok';
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
        } catch (Throwable) {
            $database = 'down';
        }

        $healthy = 'ok' === $database;

        $response = new JsonResponse([
            'status' => $healthy ? 'ok' : 'degraded',
            'database' => $database,
            'time' => $this->clock->now()->format(\DATE_ATOM),
        ], $healthy ? 200 : 503);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
