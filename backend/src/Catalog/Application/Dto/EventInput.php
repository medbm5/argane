<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Studio event form. */
final class EventInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 140)]
        public string $title = '',
        #[Assert\Length(max: 160)]
        public ?string $subtitle = null,
        #[Assert\Choice(choices: ['CONCERT', 'THEATRE', 'CONFERENCE', 'DANCE', 'COMEDY'])]
        public string $category = 'THEATRE',
        #[Assert\Length(max: 4000)]
        public string $description = '',
        #[Assert\Range(min: 10, max: 600)]
        public int $durationMinutes = 90,
        #[Assert\Range(min: 0, max: 21)]
        public ?int $ageRating = null,
        #[Assert\Length(max: 255)]
        #[Assert\Regex('#^/[A-Za-z0-9/_.-]+$#', message: 'Chemin d’affiche invalide.')]
        public ?string $posterPath = null,
    ) {
    }
}
