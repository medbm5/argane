<?php

declare(strict_types=1);

namespace App\Venue\Domain\Model;

use App\Shared\Domain\Exception\ConflictException;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A seating configuration of the hall (théâtre, concert…), drawn in a fixed
 * 1000 × 700 viewBox with the stage at the top.
 */
#[ORM\Entity]
#[ORM\Table(name: 'hall_layout')]
#[ORM\Index(name: 'idx_layout_venue', columns: ['venue_id'])]
class HallLayout
{
    public const int VIEWBOX_WIDTH = 1000;
    public const int VIEWBOX_HEIGHT = 700;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    /** Set when the first ticket is sold for a performance using this layout. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lockedAt = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?HallLayout $clonedFrom = null;

    /** @var Collection<int, Zone> */
    #[ORM\OneToMany(targetEntity: Zone::class, mappedBy: 'layout', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $zones;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Venue::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Venue $venue,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
        $this->zones = new ArrayCollection();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function venue(): Venue
    {
        return $this->venue;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function markDefault(bool $default = true): void
    {
        $this->isDefault = $default;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function archivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function archive(DateTimeImmutable $now): void
    {
        $this->archivedAt ??= $now;
        $this->isDefault = false;
    }

    public function isLocked(): bool
    {
        return null !== $this->lockedAt;
    }

    public function lockedAt(): ?DateTimeImmutable
    {
        return $this->lockedAt;
    }

    /** Freeze the geometry: sold tickets point at these seats. Idempotent. */
    public function lock(DateTimeImmutable $now): void
    {
        $this->lockedAt ??= $now;
    }

    public function clonedFrom(): ?self
    {
        return $this->clonedFrom;
    }

    /**
     * @throws ConflictException when the layout is locked
     */
    public function assertEditable(): void
    {
        if ($this->isLocked()) {
            throw ConflictException::because('LAYOUT_LOCKED', sprintf('La configuration « %s » est utilisée par des ventes. Dupliquez-la pour la modifier.', $this->name), ['layoutId' => $this->id->toRfc4122()]);
        }
    }

    /**
     * "Clone & edit": a deep, unlocked copy with fresh ids for zones and seats.
     */
    public function cloneAs(string $name, DateTimeImmutable $now): self
    {
        $copy = new self($this->venue, $name, $now);
        $copy->clonedFrom = $this;
        foreach ($this->zones() as $zone) {
            $zoneCopy = new Zone(
                $copy,
                $zone->code(),
                $zone->name(),
                $zone->kind(),
                $zone->colorToken(),
                $zone->sortOrder(),
                $zone->shape(),
                $zone->capacity(),
            );
            foreach ($zone->seats() as $seat) {
                new Seat($zoneCopy, $seat->rowLabel(), $seat->number(), $seat->x(), $seat->y(), $seat->isAccessible(), $seat->isBlocked());
            }
        }

        return $copy;
    }

    public function addZone(Zone $zone): void
    {
        $this->assertEditable();
        if (!$this->zones->contains($zone)) {
            $this->zones->add($zone);
        }
    }

    /**
     * @return list<Zone>
     */
    public function zones(): array
    {
        $zones = $this->zones->toArray();
        usort($zones, static fn (Zone $a, Zone $b): int => $a->sortOrder() <=> $b->sortOrder());

        return $zones;
    }

    public function zoneByCode(string $code): ?Zone
    {
        foreach ($this->zones as $zone) {
            if ($zone->code() === $code) {
                return $zone;
            }
        }

        return null;
    }

    /** Seats that can be sold (blocked seats excluded) plus standing capacity. */
    public function capacity(): int
    {
        return array_sum(array_map(static fn (Zone $z): int => $z->sellableCapacity(), $this->zones->toArray()));
    }
}
