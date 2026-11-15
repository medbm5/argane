<?php

declare(strict_types=1);

namespace App\Venue\Application\Dto;

use App\Venue\Domain\Model\ZoneKind;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form input for one zone of a generated layout.
 *
 * Seated zones: `rowLabels` × `seatsPerRow`, seats referenced as "A-1".
 */
final readonly class ZoneSpec
{
    /**
     * @param list<string> $rowLabels       e.g. ["A", "B", …] (see LayoutGenerator::rowRange)
     * @param list<string> $accessibleSeats seat labels like "A-1"
     * @param list<string> $blockedSeats    seat labels like "M-10"
     */
    public function __construct(
        #[Assert\NotBlank, Assert\Regex('/^[A-Z0-9_]{2,32}$/')]
        public string $code,
        #[Assert\NotBlank, Assert\Length(max: 80)]
        public string $name,
        public ZoneKind $kind,
        #[Assert\Valid]
        public Area $area,
        #[Assert\NotBlank]
        public string $colorToken = 'sepia-600',
        #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 8)])]
        public array $rowLabels = [],
        #[Assert\Range(min: 1, max: 60)]
        public int $seatsPerRow = 1,
        public SeatArrangement $arrangement = SeatArrangement::GRID,
        /** Leave a one-seat gap after this seat number (centre aisle). */
        #[Assert\Positive]
        public ?int $aisleAfter = null,
        /** Vertical bow of rows towards the stage, in layout units (0 = straight). */
        #[Assert\Range(min: 0, max: 40)]
        public float $curve = 0,
        public array $accessibleSeats = [],
        public array $blockedSeats = [],
        #[Assert\Positive]
        public ?int $capacity = null,
    ) {
    }
}
