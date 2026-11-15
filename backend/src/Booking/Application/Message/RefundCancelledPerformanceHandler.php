<?php

declare(strict_types=1);

namespace App\Booking\Application\Message;

use App\Booking\Application\CancellationService;
use App\Catalog\Application\Message\PerformanceCancelled;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Runs on the async worker: voids and refunds every paid order of a cancelled
 * performance. Idempotent (already refunded orders are no longer PAID; each refund is
 * keyed), so a retry after a crash finishes the job without paying twice.
 */
#[AsMessageHandler]
final readonly class RefundCancelledPerformanceHandler
{
    public function __construct(
        private CancellationService $cancellations,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PerformanceCancelled $message): void
    {
        $count = $this->cancellations->cancelForPerformance(
            Uuid::fromString($message->performanceId),
            null === $message->cancelledBy ? null : Uuid::fromString($message->cancelledBy),
        );
        $this->logger->info('Refunded {count} orders of cancelled performance {performance}', ['count' => $count, 'performance' => $message->performanceId]);
    }
}
