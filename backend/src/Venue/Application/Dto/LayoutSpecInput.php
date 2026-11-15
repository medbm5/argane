<?php

declare(strict_types=1);

namespace App\Venue\Application\Dto;

use App\Venue\Application\LayoutGenerator;
use App\Venue\Domain\Model\ZoneKind;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON form of a layout spec, as sent by the Studio generator form. Rows are given as a
 * range ("A" → "L") the generator expands, skipping I and O on request.
 */
final class LayoutSpecInput
{
    /**
     * @param list<array{code: string, name: string, kind: string, x: float|int, y: float|int, width: float|int, height: float|int, colorToken?: string, rowFrom?: string, rowTo?: string, skipConfusableRows?: bool, seatsPerRow?: int, arrangement?: string, aisleAfter?: int|null, curve?: float|int, accessibleSeats?: list<string>, blockedSeats?: list<string>, capacity?: int|null}> $zones
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $name = '',
        #[Assert\Count(min: 1, max: 12)]
        public array $zones = [],
    ) {
    }

    public function toSpec(): LayoutSpec
    {
        $zones = [];
        foreach ($this->zones as $zone) {
            $kind = ZoneKind::from(strtoupper($zone['kind']));
            $rows = ZoneKind::STANDING === $kind || !isset($zone['rowFrom'], $zone['rowTo'])
                ? []
                : LayoutGenerator::rowRange($zone['rowFrom'], $zone['rowTo'], $zone['skipConfusableRows'] ?? false);
            $zones[] = new ZoneSpec(
                strtoupper(trim($zone['code'])),
                trim($zone['name']),
                $kind,
                new Area((float) $zone['x'], (float) $zone['y'], (float) $zone['width'], (float) $zone['height']),
                $zone['colorToken'] ?? 'sepia-600',
                $rows,
                max(1, (int) ($zone['seatsPerRow'] ?? 1)),
                SeatArrangement::from(strtoupper($zone['arrangement'] ?? 'GRID')),
                $zone['aisleAfter'] ?? null,
                (float) ($zone['curve'] ?? 0),
                array_values($zone['accessibleSeats'] ?? []),
                array_values($zone['blockedSeats'] ?? []),
                ZoneKind::STANDING === $kind ? ($zone['capacity'] ?? null) : null,
            );
        }

        return new LayoutSpec(trim($this->name), $zones);
    }
}
