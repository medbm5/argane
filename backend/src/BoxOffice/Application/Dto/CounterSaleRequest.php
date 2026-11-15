<?php

declare(strict_types=1);

namespace App\BoxOffice\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Body of POST /api/box-office/holds/{id}/sell. */
final class CounterSaleRequest
{
    /**
     * @param list<array{seatId: string, tariffId: string}>                $seats
     * @param list<array{zoneId: string, tariffId: string, quantity: int}> $standing
     */
    public function __construct(
        #[Assert\All([new Assert\Collection([
            'seatId' => [new Assert\NotBlank(), new Assert\Uuid()],
            'tariffId' => [new Assert\NotBlank(), new Assert\Uuid()],
        ])])]
        public array $seats = [],
        #[Assert\All([new Assert\Collection([
            'zoneId' => [new Assert\NotBlank(), new Assert\Uuid()],
            'tariffId' => [new Assert\NotBlank(), new Assert\Uuid()],
            'quantity' => [new Assert\Type('int'), new Assert\Positive()],
        ])])]
        public array $standing = [],
        /** CASH or CARD_TERMINAL; ignored when nothing is due (invitations). */
        #[Assert\Choice(choices: ['CASH', 'CARD_TERMINAL', 'NONE'])]
        public string $method = 'CASH',
        /** Required for invitations. */
        #[Assert\Length(max: 255)]
        public ?string $reason = null,
        #[Assert\Length(max: 120)]
        public ?string $buyerName = null,
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public ?string $buyerEmail = null,
    ) {
    }
}
