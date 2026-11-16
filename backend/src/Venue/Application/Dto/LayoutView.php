<?php

declare(strict_types=1);

namespace App\Venue\Application\Dto;

use App\Venue\Domain\Model\HallLayout;
use DateTimeInterface;

/** A hall configuration with its full geometry (Studio list, editor preview). */
final readonly class LayoutView
{
    /**
     * @param array{width: int, height: int}                                                                                                       $viewBox
     * @param array{x: int, y: int, width: int, height: int}                                                                                       $stage
     * @param list<array{code: string, name: string, kind: string, colorToken: string, shape: string, capacity: int, seats: int}>                  $zones
     * @param list<array{id: ?string, zone: string, row: string, number: int, label: string, x: float, y: float, accessible: bool, blocked: bool}> $seats
     */
    public function __construct(
        public ?string $id,
        public string $name,
        public bool $isDefault,
        public bool $locked,
        public bool $archived,
        public ?string $clonedFromName,
        public int $capacity,
        public ?string $createdAt,
        public array $viewBox,
        public array $stage,
        public array $zones,
        public array $seats,
    ) {
    }

    public static function of(HallLayout $layout, bool $persisted = true, bool $withSeats = true): self
    {
        $zones = [];
        $seats = [];
        foreach ($layout->zones() as $zone) {
            $zoneSeats = $zone->seats();
            $zones[] = [
                'code' => $zone->code(),
                'name' => $zone->name(),
                'kind' => $zone->kind()->value,
                'colorToken' => $zone->colorToken(),
                'shape' => $zone->shape(),
                'capacity' => $zone->sellableCapacity(),
                'seats' => \count($zoneSeats),
            ];
            if ($withSeats) {
                foreach ($zoneSeats as $seat) {
                    $seats[] = [
                        'id' => $persisted ? $seat->id()->toRfc4122() : null,
                        'zone' => $zone->code(),
                        'row' => $seat->rowLabel(),
                        'number' => $seat->number(),
                        'label' => $seat->label(),
                        'x' => round($seat->x(), 2),
                        'y' => round($seat->y(), 2),
                        'accessible' => $seat->isAccessible(),
                        'blocked' => $seat->isBlocked(),
                    ];
                }
            }
        }

        return new self(
            $persisted ? $layout->id()->toRfc4122() : null,
            $layout->name(),
            $layout->isDefault(),
            $layout->isLocked(),
            null !== $layout->archivedAt(),
            $layout->clonedFrom()?->name(),
            $layout->capacity(),
            $persisted ? $layout->createdAt()->format(DateTimeInterface::ATOM) : null,
            ['width' => HallLayout::VIEWBOX_WIDTH, 'height' => HallLayout::VIEWBOX_HEIGHT],
            HallLayout::STAGE,
            $zones,
            $seats,
        );
    }
}
