<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\InventoryRepository;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Venue\Application\Dto\LayoutView;
use App\Venue\Domain\VenueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The Studio's "Salle": the organizer switches seats of a plan on and off. The plan
 * changes for dates scheduled later, and every upcoming date follows where the seat is
 * still free; a sold seat, or one being paid for, is never taken back.
 */
final readonly class HallSeats
{
    public function __construct(
        private VenueRepository $venues,
        private InventoryRepository $inventory,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $seatIds
     *
     * @return array{layout: LayoutView, seatsUpdated: int, seatsKept: int}
     */
    public function setBlocked(string $layoutId, array $seatIds, bool $blocked): array
    {
        $layout = (Uuid::isValid($layoutId) ? $this->venues->findLayout(Uuid::fromString($layoutId)) : null)
            ?? throw NotFoundException::of('Configuration', $layoutId);

        $byId = [];
        foreach ($layout->zones() as $zone) {
            foreach ($zone->seats() as $seat) {
                $byId[$seat->id()->toRfc4122()] = $seat;
            }
        }
        $seats = [];
        foreach (array_unique($seatIds) as $id) {
            $seats[] = $byId[strtolower($id)] ?? throw BusinessRuleException::because('SEAT_NOT_IN_LAYOUT', 'Cette place ne fait pas partie de ce plan.', ['seatId' => $id]);
        }
        if ([] === $seats) {
            throw BusinessRuleException::because('NO_SEAT_SELECTED', 'Sélectionnez au moins une place.');
        }

        $now = $this->clock->now();

        return $this->em->wrapInTransaction(function () use ($seats, $blocked, $layout, $now): array {
            foreach ($seats as $seat) {
                $seat->setBlocked($blocked);
            }
            $this->em->flush();
            $result = $this->inventory->applySeatBlocking(array_map(static fn ($s): Uuid => $s->id(), $seats), $blocked, $now);

            return ['layout' => LayoutView::of($layout), 'seatsUpdated' => $result['updated'], 'seatsKept' => $result['kept']];
        });
    }
}
