<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\InventoryRepository;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\PerformanceRepository;
use App\Shared\Domain\Exception\ConflictException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Publishing a performance makes it visible and creates its inventory
 * (one row per seat, one stock row per standing zone) in a single transaction.
 */
final readonly class PerformancePublisher
{
    public function __construct(
        private PerformanceRepository $performances,
        private InventoryRepository $inventory,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    public function publish(Performance $performance, bool $openSales = false): void
    {
        $now = $this->clock->now();
        $performance->assertNotCancelled();
        if (!$performance->event()->isPublished()) {
            throw ConflictException::because('EVENT_NOT_PUBLISHED', 'Publiez d’abord le spectacle.');
        }
        if ($performance->isPast($now)) {
            throw ConflictException::because('PERFORMANCE_PAST', 'Cette représentation a déjà eu lieu.');
        }
        if ([] === $performance->layout()->zones()) {
            throw ConflictException::because('LAYOUT_EMPTY', 'La configuration de salle ne contient aucune zone.');
        }

        $this->em->wrapInTransaction(function () use ($performance, $now, $openSales): void {
            $this->inventory->generate($performance, $now);
            $performance->markPublished($now);
            if ($openSales) {
                $performance->openSales($now);
            }
            $this->performances->save($performance);
        });
    }
}
