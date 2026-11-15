<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Message\PerformanceCancelled;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\PerformanceRepository;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\NotFoundException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Cancels a performance: sales stop at once (no new holds, no checkout), then the
 * refunds run in the background so the organiser is not kept waiting.
 */
final readonly class PerformanceCancellation
{
    public function __construct(
        private PerformanceRepository $performances,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    public function cancel(string $performanceId, string $reason, Uuid $organizerId): Performance
    {
        $performance = Uuid::isValid($performanceId) ? $this->performances->find(Uuid::fromString($performanceId)) : null;
        if (null === $performance) {
            throw NotFoundException::of('Représentation', $performanceId);
        }
        if ('' === trim($reason)) {
            throw BusinessRuleException::because('REASON_REQUIRED', 'Indiquez la raison de l’annulation : elle est communiquée aux spectateurs.');
        }
        if ($performance->isPast($this->clock->now())) {
            throw BusinessRuleException::because('PERFORMANCE_PAST', 'Une représentation passée ne peut plus être annulée.', [], 409);
        }

        $performance->cancel(trim($reason), $this->clock->now());
        $this->performances->save($performance);
        $this->bus->dispatch(new PerformanceCancelled($performance->id()->toRfc4122(), $organizerId->toRfc4122(), trim($reason)));

        return $performance;
    }
}
