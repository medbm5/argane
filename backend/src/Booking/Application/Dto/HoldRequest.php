<?php

declare(strict_types=1);

namespace App\Booking\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Body of POST /api/performances/{id}/holds. */
final class HoldRequest
{
    /**
     * @param list<string>                               $seatIds  performance seat ids
     * @param list<array{zoneId: string, quantity: int}> $standing
     */
    public function __construct(
        #[Assert\All([new Assert\Uuid()])]
        #[Assert\Unique]
        public array $seatIds = [],
        #[Assert\All([new Assert\Collection([
            'zoneId' => [new Assert\NotBlank(), new Assert\Uuid()],
            'quantity' => [new Assert\Type('int'), new Assert\Positive()],
        ])])]
        public array $standing = [],
        #[Assert\Choice(choices: ['ONLINE', 'BOX_OFFICE'])]
        public string $channel = 'ONLINE',
    ) {
    }
}
