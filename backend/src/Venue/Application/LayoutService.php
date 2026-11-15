<?php

declare(strict_types=1);

namespace App\Venue\Application;

use App\Shared\Domain\Exception\NotFoundException;
use App\Venue\Domain\Model\HallLayout;
use App\Venue\Domain\VenueRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Layout lifecycle used by the Studio and by other modules
 * (Booking locks a layout when the first ticket is sold on it).
 */
final readonly class LayoutService
{
    public function __construct(
        private VenueRepository $venues,
        private ClockInterface $clock,
    ) {
    }

    public function get(Uuid $id): HallLayout
    {
        return $this->venues->findLayout($id) ?? throw NotFoundException::of('Configuration de salle', $id->toRfc4122());
    }

    public function clone(Uuid $id, ?string $name = null): HallLayout
    {
        $source = $this->get($id);
        $copy = $source->cloneAs($name ?? $source->name().' (copie)', $this->clock->now());
        $this->venues->saveLayout($copy);

        return $copy;
    }

    public function lock(Uuid $id): void
    {
        $layout = $this->get($id);
        if (!$layout->isLocked()) {
            $layout->lock($this->clock->now());
            $this->venues->saveLayout($layout);
        }
    }

    public function archive(Uuid $id): void
    {
        $layout = $this->get($id);
        $layout->archive($this->clock->now());
        $this->venues->saveLayout($layout);
    }
}
