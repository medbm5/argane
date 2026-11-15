<?php

declare(strict_types=1);

namespace App\Pricing\Domain;

use App\Catalog\Domain\Model\Performance;
use App\Pricing\Domain\Model\PerformancePrice;

interface PriceRepository
{
    /**
     * @return list<PerformancePrice>
     */
    public function ofPerformance(Performance $performance): array;

    /**
     * Replace the whole grid of a performance atomically.
     *
     * @param list<PerformancePrice> $prices
     */
    public function replaceGrid(Performance $performance, array $prices): void;
}
