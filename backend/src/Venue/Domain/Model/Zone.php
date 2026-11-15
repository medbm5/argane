<?php

declare(strict_types=1);

namespace App\Venue\Domain\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'zone')]
#[ORM\UniqueConstraint(name: 'uniq_zone_layout_code', columns: ['layout_id', 'code'])]
class Zone
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    /** @var Collection<int, Seat> */
    #[ORM\OneToMany(targetEntity: Seat::class, mappedBy: 'zone', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $seats;

    /**
     * @param string $shape SVG path in layout coordinates
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: HallLayout::class, inversedBy: 'zones')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private HallLayout $layout,
        #[ORM\Column(length: 32)]
        private string $code,
        #[ORM\Column(length: 80)]
        private string $name,
        #[ORM\Column(length: 16, enumType: ZoneKind::class)]
        private ZoneKind $kind,
        #[ORM\Column(length: 32)]
        private string $colorToken,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $sortOrder,
        #[ORM\Column(type: Types::TEXT)]
        private string $shape,
        #[ORM\Column(nullable: true)]
        private ?int $capacity = null,
    ) {
        $this->id = Uuid::v7();
        $this->seats = new ArrayCollection();
        $layout->addZone($this);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function layout(): HallLayout
    {
        return $this->layout;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function kind(): ZoneKind
    {
        return $this->kind;
    }

    public function isStanding(): bool
    {
        return ZoneKind::STANDING === $this->kind;
    }

    public function colorToken(): string
    {
        return $this->colorToken;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function shape(): string
    {
        return $this->shape;
    }

    /** Standing capacity; null for seated zones (capacity = their seats). */
    public function capacity(): ?int
    {
        return $this->capacity;
    }

    public function addSeat(Seat $seat): void
    {
        $this->layout->assertEditable();
        if (!$this->seats->contains($seat)) {
            $this->seats->add($seat);
        }
    }

    /**
     * @return list<Seat>
     */
    public function seats(): array
    {
        return array_values($this->seats->toArray());
    }

    public function sellableCapacity(): int
    {
        if ($this->isStanding()) {
            return $this->capacity ?? 0;
        }

        return \count(array_filter($this->seats->toArray(), static fn (Seat $s): bool => !$s->isBlocked()));
    }
}
