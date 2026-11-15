<?php

declare(strict_types=1);

namespace App\Integration\Application\Message;

use App\Integration\Application\SheetsSync;
use App\Shared\Application\Message\SalesChanged;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sales changed (delayed a few seconds by the publisher): rebuild the spreadsheet,
 * unless a rewrite already ran after this change, so a burst of sales costs one sync.
 * The hourly schedule (SyncSheets) catches anything a failure may have missed.
 */
final readonly class SyncSheetsHandler
{
    public function __construct(private SheetsSync $sync)
    {
    }

    #[AsMessageHandler]
    public function onSalesChanged(SalesChanged $message): void
    {
        $this->sync->sync($message->at);
    }

    #[AsMessageHandler]
    public function onSchedule(SyncSheets $message): void
    {
        $this->sync->sync();
    }
}
