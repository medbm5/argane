<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Model;

use App\Catalog\Domain\Model\Performance;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Money;
use App\Venue\Domain\Model\Zone;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One cell of a performance's price grid. A missing cell means the tariff
 * is not offered in that zone. Sold tickets keep their own price snapshot.
 */
#[ORM\Entity]
#[ORM\Table(name: 'performance_price')]
#[ORM\UniqueConstraint(name: 'uniq_perf_price', columns: ['performance_id', 'zone_id', 'tariff_id'])]
class PerformancePrice
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Performance::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Performance $performance,
        #[ORM\ManyToOne(targetEntity: Zone::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Zone $zone,
        #[ORM\ManyToOne(targetEntity: Tariff::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Tariff $tariff,
        #[ORM\Column]
        private int $amountCents,
    ) {
        $this->id = Uuid::v7();
        if ($zone->layout() !== $performance->layout()) {
            throw BusinessRuleException::because('PRICE_ZONE_MISMATCH', 'Cette zone n’appartient pas à la configuration de la représentation.');
        }
        $this->assertAmount($amountCents);
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

    public function tariff(): Tariff
    {
        return $this->tariff;
    }

    public function amountCents(): int
    {
        return $this->amountCents;
    }

    public function amount(string $currency): Money
    {
        return Money::of($this->amountCents, $currency);
    }

    public function changeAmount(int $amountCents): void
    {
        $this->assertAmount($amountCents);
        $this->amountCents = $amountCents;
    }

    private function assertAmount(int $amountCents): void
    {
        if ($amountCents < 0) {
            throw BusinessRuleException::because('PRICE_NEGATIVE', 'Un prix ne peut pas être négatif.');
        }
        if ($this->tariff->isFree() && 0 !== $amountCents) {
            throw BusinessRuleException::because('PRICE_FREE_TARIFF', sprintf('Le tarif %s est gratuit.', $this->tariff->label()));
        }
        if (!$this->tariff->isFree() && 0 === $amountCents) {
            throw BusinessRuleException::because('PRICE_ZERO', sprintf('Le tarif %s doit avoir un prix.', $this->tariff->label()));
        }
    }
}
