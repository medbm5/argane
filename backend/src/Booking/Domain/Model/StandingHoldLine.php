<?php

declare(strict_types=1);

namespace App\Booking\Domain\Model;

use App\Venue\Domain\Model\Zone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** Quantity of standing places held in one zone. */
#[ORM\Entity]
#[ORM\Table(name: 'standing_hold_line')]
#[ORM\UniqueConstraint(name: 'uniq_standing_line', columns: ['hold_id', 'zone_id'])]
class StandingHoldLine
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Hold::class, inversedBy: 'standingLines')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Hold $hold,
        #[ORM\ManyToOne(targetEntity: Zone::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Zone $zone,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $quantity,
    ) {
        $this->id = Uuid::v7();
        $hold->addStandingLine($this);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function hold(): Hold
    {
        return $this->hold;
    }

    public function zone(): Zone
    {
        return $this->zone;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }
}
