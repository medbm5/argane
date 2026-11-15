<?php

declare(strict_types=1);

namespace App\Pricing\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Studio tariff form. */
final class TariffInput
{
    /**
     * @param list<string> $channels
     * @param list<string> $allowedRoles
     */
    public function __construct(
        #[Assert\Regex('/^[A-Z0-9_]{2,32}$/', message: 'Code en majuscules, sans espace.')]
        public string $code = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 80)]
        public string $label = '',
        #[Assert\Count(min: 1)]
        #[Assert\All([new Assert\Choice(choices: ['ONLINE', 'BOX_OFFICE'])])]
        public array $channels = ['ONLINE', 'BOX_OFFICE'],
        #[Assert\All([new Assert\Choice(choices: ['ROLE_BOX_OFFICE', 'ROLE_ORGANIZER'])])]
        public array $allowedRoles = [],
        #[Assert\Range(min: 1, max: 100)]
        public int $minQuantity = 1,
        #[Assert\Range(min: 1, max: 100)]
        public ?int $maxQuantity = null,
        public bool $requiresProofAtEntry = false,
        public bool $isFree = false,
        #[Assert\Length(max: 255)]
        public ?string $description = null,
        #[Assert\Range(min: 0, max: 100)]
        public int $sortOrder = 0,
    ) {
    }
}
