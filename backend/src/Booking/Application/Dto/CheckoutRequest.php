<?php

declare(strict_types=1);

namespace App\Booking\Application\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of POST /api/holds/{id}/checkout: a tariff for every held seat and a split of
 * every held standing quantity by tariff. Must cover the hold exactly.
 */
final class CheckoutRequest
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
        /** Required for invitations (box office). */
        #[Assert\Length(max: 255)]
        public ?string $reason = null,
    ) {
    }
}
