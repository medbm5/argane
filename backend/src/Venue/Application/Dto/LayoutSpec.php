<?php

declare(strict_types=1);

namespace App\Venue\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LayoutSpec
{
    /**
     * @param list<ZoneSpec> $zones
     */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 120)]
        public string $name,
        #[Assert\Count(min: 1, max: 12), Assert\Valid]
        public array $zones,
        public bool $isDefault = false,
    ) {
    }
}
