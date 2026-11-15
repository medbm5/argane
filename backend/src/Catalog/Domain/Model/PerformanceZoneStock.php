<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Venue\Domain\Model\Zone;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Standing zone stock for one performance. Holds lock this row (SELECT … FOR UPDATE)
 * and compare capacity − soldCount − active held quantity.
 */
#[ORM\Entity]
#[ORM\Table(name: 'performance_zone_stock')]
#[ORM\UniqueConstraint(name: 'uniq_perf_zone_stock', columns: ['performance_id', 'zone_id'])]
class PerformanceZoneStock
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column]
    private int $soldCount = 0;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Performance::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Performance $performance,
        #[ORM\ManyToOne(targetEntity: Zone::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Zone $zone,
        #[ORM\Column]
        private int $capacity,
    ) {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function performance(): Performance
    {
        return $this->performance;
    }

    public function zone(): Zone
    {
        return $this->zone;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function soldCount(): int
    {
        return $this->soldCount;
    }

    public function remaining(): int
    {
        return max(0, $this->capacity - $this->soldCount);
    }
}
