<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Booking\Application\Message\ExpireHolds;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/** Recurring jobs, run by `messenger:consume scheduler_default` (supervised in production). */
#[AsSchedule]
final readonly class Schedule implements ScheduleProviderInterface
{
    public function __construct(private CacheInterface $cache)
    {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(RecurringMessage::every('1 minute', new ExpireHolds()));
    }
}
