<?php

declare(strict_types=1);

namespace App\Venue\Api\Dto;

final readonly class LayoutSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public int $capacity,
        public bool $isDefault,
    ) {
    }
}
