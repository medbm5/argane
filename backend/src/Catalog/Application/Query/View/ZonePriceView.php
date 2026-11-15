<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

/** A tariff the caller may buy in a zone, with its price. */
final readonly class ZonePriceView
{
    public function __construct(
        public string $tariffId,
        public string $tariffCode,
        public string $label,
        public ?string $description,
        public int $amountCents,
        public int $minQuantity,
        public ?int $maxQuantity,
        public bool $requiresProofAtEntry,
        public bool $requiresReason,
    ) {
    }
}
