<?php

declare(strict_types=1);

namespace App\Pricing\Application\Dto;

/** A performance's grid: zones as rows, tariffs as columns, cells in minor units. */
final readonly class PriceGridView
{
    /**
     * @param list<array{id: string, code: string, name: string, kind: string}> $zones
     * @param list<TariffView>                                                  $tariffs
     * @param list<array{zoneId: string, tariffId: string, amountCents: int}>   $cells
     */
    public function __construct(
        public string $performanceId,
        public string $currency,
        public array $zones,
        public array $tariffs,
        public array $cells,
    ) {
    }
}
