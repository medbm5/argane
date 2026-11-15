<?php

declare(strict_types=1);

namespace App\Booking\Application\Message;

use App\Booking\Application\HoldService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reads never depend on this (expiry is applied lazily in every query); it only keeps
 * the hold and seat tables tidy and statuses truthful for reporting.
 */
#[AsMessageHandler]
final readonly class ExpireHoldsHandler
{
    public function __construct(private HoldService $holds, private LoggerInterface $logger)
    {
    }

    public function __invoke(ExpireHolds $message): void
    {
        $expired = $this->holds->expireDue();
        if ($expired > 0) {
            $this->logger->info('Expired {count} holds', ['count' => $expired]);
        }
    }
}
