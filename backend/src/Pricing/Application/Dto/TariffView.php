<?php

declare(strict_types=1);

namespace App\Pricing\Application\Dto;

use App\Pricing\Domain\Model\Tariff;

final readonly class TariffView
{
    /**
     * @param list<string> $channels
     * @param list<string> $allowedRoles
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $label,
        public ?string $description,
        public array $channels,
        public array $allowedRoles,
        public int $minQuantity,
        public ?int $maxQuantity,
        public bool $requiresProofAtEntry,
        public bool $isFree,
        public bool $active,
        public int $sortOrder,
    ) {
    }

    public static function of(Tariff $t): self
    {
        return new self(
            $t->id()->toRfc4122(),
            $t->code(),
            $t->label(),
            $t->description(),
            array_map(static fn ($c): string => $c->value, $t->channels()),
            $t->allowedRoles(),
            $t->minQuantity(),
            $t->maxQuantity(),
            $t->requiresProofAtEntry(),
            $t->isFree(),
            $t->isActive(),
            $t->sortOrder(),
        );
    }
}
