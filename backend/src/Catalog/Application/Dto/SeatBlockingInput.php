<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SeatBlockingInput
{
    /**
     * @param list<string> $seatIds plan seat ids
     */
    public function __construct(
        #[Assert\Count(min: 1, max: 1000)]
        #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 36)])]
        public array $seatIds,
        public bool $blocked,
    ) {
    }
}
