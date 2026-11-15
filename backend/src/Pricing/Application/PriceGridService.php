<?php

declare(strict_types=1);

namespace App\Pricing\Application;

use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\PerformanceRepository;
use App\Pricing\Domain\Model\PerformancePrice;
use App\Pricing\Domain\PriceRepository;
use App\Pricing\Domain\TariffRepository;
use App\Shared\Domain\Exception\BusinessRuleException;
use Symfony\Component\Uid\Uuid;

/**
 * Edits the zone × tariff price grid of performances.
 */
final readonly class PriceGridService
{
    public function __construct(
        private PriceRepository $prices,
        private TariffRepository $tariffs,
        private PerformanceRepository $performances,
    ) {
    }

    /**
     * Full replacement of a performance grid. Cells with a null amount are removed.
     *
     * @param list<array{zoneId: string, tariffId: string, amountCents: int|null}> $cells
     *
     * @return list<PerformancePrice>
     */
    public function setGrid(Performance $performance, array $cells): array
    {
        $performance->assertNotCancelled();
        $zones = [];
        foreach ($performance->layout()->zones() as $zone) {
            $zones[$zone->id()->toRfc4122()] = $zone;
        }

        $grid = [];
        $seen = [];
        foreach ($cells as $cell) {
            if (null === $cell['amountCents']) {
                continue;
            }
            $zone = $zones[Uuid::fromString($cell['zoneId'])->toRfc4122()] ?? throw BusinessRuleException::because('PRICE_ZONE_MISMATCH', 'Zone inconnue pour cette représentation.', ['zoneId' => $cell['zoneId']]);
            $tariff = $this->tariffs->find(Uuid::fromString($cell['tariffId']));
            if (null === $tariff || $tariff->venue() !== $performance->event()->venue()) {
                throw BusinessRuleException::because('PRICE_UNKNOWN_TARIFF', 'Tarif inconnu.', ['tariffId' => $cell['tariffId']]);
            }
            $key = $zone->id()->toRfc4122().'|'.$tariff->id()->toRfc4122();
            if (isset($seen[$key])) {
                throw BusinessRuleException::because('PRICE_DUPLICATE_CELL', 'La grille contient deux prix pour la même zone et le même tarif.');
            }
            $seen[$key] = true;
            $grid[] = new PerformancePrice($performance, $zone, $tariff, $cell['amountCents']);
        }

        $this->prices->replaceGrid($performance, $grid);

        return $grid;
    }

    /**
     * Copies a performance grid to every other performance of the same event,
     * matching zones by code (performances may use different layouts).
     *
     * @return int number of performances updated
     */
    public function applyToAll(Performance $source): int
    {
        $cellsByZoneCode = [];
        foreach ($this->prices->ofPerformance($source) as $price) {
            $cellsByZoneCode[$price->zone()->code()][] = ['tariffId' => $price->tariff()->id()->toRfc4122(), 'amountCents' => $price->amountCents()];
        }

        $updated = 0;
        foreach ($this->performances->ofEvent($source->event()) as $target) {
            if ($target === $source || $target->isCancelled()) {
                continue;
            }
            $cells = [];
            foreach ($target->layout()->zones() as $zone) {
                foreach ($cellsByZoneCode[$zone->code()] ?? [] as $cell) {
                    $cells[] = ['zoneId' => $zone->id()->toRfc4122(), ...$cell];
                }
            }
            $this->setGrid($target, $cells);
            ++$updated;
        }

        return $updated;
    }
}
