<?php

declare(strict_types=1);

namespace App\Venue\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'seat')]
#[ORM\UniqueConstraint(name: 'uniq_seat_zone_row_number', columns: ['zone_id', 'row_label', 'number'])]
class Seat
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Zone::class, inversedBy: 'seats')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Zone $zone,
        #[ORM\Column(length: 8)]
        private string $rowLabel,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $number,
        #[ORM\Column(type: Types::FLOAT)]
        private float $x,
        #[ORM\Column(type: Types::FLOAT)]
        private float $y,
        #[ORM\Column]
        private bool $isAccessible = false,
        #[ORM\Column]
        private bool $isBlocked = false,
    ) {
        $this->id = Uuid::v7();
        $zone->addSeat($this);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function zone(): Zone
    {
        return $this->zone;
    }

    public function rowLabel(): string
    {
        return $this->rowLabel;
    }

    public function number(): int
    {
        return $this->number;
    }

    /** Human label, e.g. "C-12". */
    public function label(): string
    {
        return $this->rowLabel.'-'.$this->number;
    }

    public function x(): float
    {
        return $this->x;
    }

    public function y(): float
    {
        return $this->y;
    }

    public function isAccessible(): bool
    {
        return $this->isAccessible;
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    /**
     * Takes the seat out of the plan (broken, technical position…) or puts it back.
     * Not a structural change, so allowed on a locked configuration.
     */
    public function setBlocked(bool $blocked): void
    {
        $this->isBlocked = $blocked;
    }
}
