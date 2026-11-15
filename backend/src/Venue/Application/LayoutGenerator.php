<?php

declare(strict_types=1);

namespace App\Venue\Application;

use App\Shared\Domain\Exception\BusinessRuleException;
use App\Venue\Application\Dto\Area;
use App\Venue\Application\Dto\LayoutSpec;
use App\Venue\Application\Dto\SeatArrangement;
use App\Venue\Application\Dto\ZoneSpec;
use App\Venue\Domain\Model\HallLayout;
use App\Venue\Domain\Model\Seat;
use App\Venue\Domain\Model\Venue;
use App\Venue\Domain\Model\Zone;
use App\Venue\Domain\Model\ZoneKind;
use Psr\Clock\ClockInterface;

/**
 * Builds a HallLayout (zones + seat coordinates) from a form-like spec,
 * so the organizer never draws seats by hand.
 */
final readonly class LayoutGenerator
{
    private const float PADDING = 8.0;

    public function __construct(private ClockInterface $clock)
    {
    }

    /**
     * Expands "A".."L" (or "1".."12") into row labels. Pass $skipConfusable to drop
     * "I" and "O", which some halls avoid because they read like 1 and 0.
     *
     * @return list<string>
     */
    public static function rowRange(string $from, string $to, bool $skipConfusable = false): array
    {
        if (ctype_digit($from) && ctype_digit($to)) {
            return array_map('strval', range((int) $from, (int) $to));
        }
        $labels = [];
        foreach (range(strtoupper($from), strtoupper($to)) as $letter) {
            $letter = (string) $letter;
            if ($skipConfusable && \in_array($letter, ['I', 'O'], true)) {
                continue;
            }
            $labels[] = $letter;
        }

        return $labels;
    }

    public function generate(Venue $venue, LayoutSpec $spec): HallLayout
    {
        $this->assertUniqueCodes($spec);

        $layout = new HallLayout($venue, $spec->name, $this->clock->now());
        $layout->markDefault($spec->isDefault);

        foreach ($spec->zones as $index => $zoneSpec) {
            $this->buildZone($layout, $zoneSpec, $index);
        }

        return $layout;
    }

    private function buildZone(HallLayout $layout, ZoneSpec $spec, int $index): Zone
    {
        if (!$spec->area->fitsInViewBox()) {
            throw BusinessRuleException::because('LAYOUT_ZONE_OUT_OF_BOUNDS', sprintf('La zone %s dépasse le plan de salle.', $spec->name), ['zone' => $spec->code]);
        }

        return match ($spec->kind) {
            ZoneKind::SEATED => $this->buildSeatedZone($layout, $spec, $index),
            ZoneKind::STANDING => $this->buildStandingZone($layout, $spec, $index),
        };
    }

    private function buildSeatedZone(HallLayout $layout, ZoneSpec $spec, int $index): Zone
    {
        if ([] === $spec->rowLabels) {
            throw BusinessRuleException::because('LAYOUT_ZONE_EMPTY', sprintf('La zone %s n’a aucun rang.', $spec->name), ['zone' => $spec->code]);
        }
        if (\count(array_unique($spec->rowLabels)) !== \count($spec->rowLabels)) {
            throw BusinessRuleException::because('LAYOUT_DUPLICATE_ROW', sprintf('La zone %s contient deux rangs du même nom.', $spec->name), ['zone' => $spec->code]);
        }

        $shape = SeatArrangement::BOXES === $spec->arrangement
            ? self::rectPath($spec->area).' '.self::rectPath($spec->area->mirrored())
            : self::rectPath($spec->area);

        $zone = new Zone($layout, $spec->code, $spec->name, ZoneKind::SEATED, $spec->colorToken, $index, $shape);

        $positions = SeatArrangement::BOXES === $spec->arrangement
            ? $this->boxPositions($spec)
            : $this->gridPositions($spec);

        $known = [];
        foreach ($positions as [$row, $number, $x, $y]) {
            $label = $row.'-'.$number;
            $known[$label] = true;
            new Seat(
                $zone,
                $row,
                $number,
                round($x, 1),
                round($y, 1),
                \in_array($label, $spec->accessibleSeats, true),
                \in_array($label, $spec->blockedSeats, true),
            );
        }

        $unknown = array_values(array_diff([...$spec->accessibleSeats, ...$spec->blockedSeats], array_keys($known)));
        if ([] !== $unknown) {
            throw BusinessRuleException::because('LAYOUT_UNKNOWN_SEAT', sprintf('Places inconnues dans la zone %s : %s.', $spec->name, implode(', ', $unknown)), ['zone' => $spec->code, 'seats' => $unknown]);
        }

        return $zone;
    }

    /**
     * Standing zones have no seats: a capacity sold by quantity and a shape on the plan.
     * The shape gently bows towards the stage, like a pit in front of it.
     */
    private function buildStandingZone(HallLayout $layout, ZoneSpec $spec, int $index): Zone
    {
        if (null === $spec->capacity || $spec->capacity < 1) {
            throw BusinessRuleException::because('LAYOUT_STANDING_CAPACITY', sprintf('La zone debout %s doit avoir une capacité.', $spec->name), ['zone' => $spec->code]);
        }
        if ([] !== $spec->rowLabels || [] !== $spec->accessibleSeats || [] !== $spec->blockedSeats) {
            throw BusinessRuleException::because('LAYOUT_STANDING_HAS_SEATS', sprintf('La zone debout %s ne peut pas avoir de places numérotées.', $spec->name), ['zone' => $spec->code]);
        }

        $shape = $spec->curve > 0 ? self::pitPath($spec->area, $spec->curve) : self::rectPath($spec->area);

        return new Zone($layout, $spec->code, $spec->name, ZoneKind::STANDING, $spec->colorToken, $index, $shape, $spec->capacity);
    }

    /** Rectangle whose front edge (stage side) is concave towards the stage. */
    public static function pitPath(Area $a, float $curve): string
    {
        $fmt = static fn (float $v): string => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
        $x2 = $a->x + $a->width;
        $y2 = $a->y + $a->height;

        return sprintf(
            'M%s %s Q%s %s %s %s V%s H%s Z',
            $fmt($a->x),
            $fmt($a->y + $curve),
            $fmt($a->x + $a->width / 2),
            $fmt($a->y - $curve),
            $fmt($x2),
            $fmt($a->y + $curve),
            $fmt($y2),
            $fmt($a->x),
        );
    }

    /**
     * Rows front (near the stage) to back; seats left to right, optional centre aisle and bow.
     *
     * @return list<array{string, int, float, float}>
     */
    private function gridPositions(ZoneSpec $spec): array
    {
        $area = $spec->area;
        $slots = $spec->seatsPerRow + (null !== $spec->aisleAfter && $spec->aisleAfter < $spec->seatsPerRow ? 1 : 0);
        $pitchX = ($area->width - 2 * self::PADDING) / $slots;
        $pitchY = ($area->height - 2 * self::PADDING - $spec->curve) / \count($spec->rowLabels);
        $centerX = $area->x + $area->width / 2;
        $halfWidth = max(1.0, $area->width / 2 - self::PADDING);

        $positions = [];
        foreach ($spec->rowLabels as $r => $row) {
            for ($n = 1; $n <= $spec->seatsPerRow; ++$n) {
                $slot = $n - 1 + (null !== $spec->aisleAfter && $n > $spec->aisleAfter ? 1 : 0);
                $x = $area->x + self::PADDING + $pitchX * ($slot + 0.5);
                $bow = $spec->curve * (($x - $centerX) / $halfWidth) ** 2;
                $y = $area->y + self::PADDING + $pitchY * ($r + 0.5) + $spec->curve - $bow;
                $positions[] = [$row, $n, $x, $y];
            }
        }

        return $positions;
    }

    /**
     * Boxes alternate left wall / right wall; seats inside a box in two columns.
     *
     * @return list<array{string, int, float, float}>
     */
    private function boxPositions(ZoneSpec $spec): array
    {
        $boxes = \count($spec->rowLabels);
        $perSide = (int) ceil($boxes / 2);
        $positions = [];

        foreach ($spec->rowLabels as $b => $row) {
            $side = 0 === $b % 2 ? $spec->area : $spec->area->mirrored();
            $slot = intdiv($b, 2);
            $boxHeight = ($side->height - 2 * self::PADDING) / $perSide;
            $top = $side->y + self::PADDING + $boxHeight * $slot;
            $lines = (int) ceil($spec->seatsPerRow / 2);
            $pitchY = ($boxHeight - 6) / max(1, $lines);
            $pitchX = ($side->width - 2 * self::PADDING) / 2;

            for ($n = 1; $n <= $spec->seatsPerRow; ++$n) {
                $col = ($n - 1) % 2;
                $line = intdiv($n - 1, 2);
                $x = $side->x + self::PADDING + $pitchX * ($col + 0.5);
                $y = $top + 3 + $pitchY * ($line + 0.5);
                $positions[] = [$row, $n, $x, $y];
            }
        }

        return $positions;
    }

    public static function rectPath(Area $a, float $radius = 10.0): string
    {
        $r = min($radius, $a->width / 2, $a->height / 2);
        $x2 = $a->x + $a->width;
        $y2 = $a->y + $a->height;

        return sprintf(
            'M%s %s H%s Q%s %s %s %s V%s Q%s %s %s %s H%s Q%s %s %s %s V%s Q%s %s %s %s Z',
            ...array_map(static fn (float $v): string => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.'), [
                $a->x + $r, $a->y,
                $x2 - $r,
                $x2, $a->y, $x2, $a->y + $r,
                $y2 - $r,
                $x2, $y2, $x2 - $r, $y2,
                $a->x + $r,
                $a->x, $y2, $a->x, $y2 - $r,
                $a->y + $r,
                $a->x, $a->y, $a->x + $r, $a->y,
            ]),
        );
    }

    private function assertUniqueCodes(LayoutSpec $spec): void
    {
        $codes = array_map(static fn (ZoneSpec $z): string => $z->code, $spec->zones);
        if (\count(array_unique($codes)) !== \count($codes)) {
            throw BusinessRuleException::because('LAYOUT_DUPLICATE_ZONE', 'Deux zones portent le même code.');
        }
    }
}
