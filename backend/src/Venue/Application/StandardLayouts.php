<?php

declare(strict_types=1);

namespace App\Venue\Application;

use App\Venue\Application\Dto\Area;
use App\Venue\Application\Dto\LayoutSpec;
use App\Venue\Application\Dto\SeatArrangement;
use App\Venue\Application\Dto\ZoneSpec;
use App\Venue\Domain\Model\ZoneKind;

/**
 * The two configurations of Théâtre Argane (README "Hall configurations").
 * Plan: 1000 × 700, stage at the top (y < 90).
 */
final class StandardLayouts
{
    /** Orchestre A–L × 20, Corbeille M–R × 20, Loges 8 × 5 = 400 seats. */
    public static function theatre(): LayoutSpec
    {
        return new LayoutSpec('Configuration théâtre', [
            new ZoneSpec(
                'ORCHESTRE',
                'Orchestre',
                ZoneKind::SEATED,
                new Area(150, 110, 700, 330),
                colorToken: 'bordeaux-700',
                rowLabels: LayoutGenerator::rowRange('A', 'L'),
                seatsPerRow: 20,
                aisleAfter: 10,
                curve: 18,
                accessibleSeats: ['A-1', 'A-2', 'A-19', 'A-20'],
            ),
            self::corbeille(),
            self::loges(),
        ], isDefault: true);
    }

    /** Fosse debout 300, Corbeille 120, Loges 40 = 460 places. */
    public static function concert(): LayoutSpec
    {
        return new LayoutSpec('Configuration concert', [
            new ZoneSpec(
                'FOSSE',
                'Fosse debout',
                ZoneKind::STANDING,
                new Area(150, 110, 700, 330),
                colorToken: 'gilt-400',
                curve: 16,
                capacity: 300,
            ),
            self::corbeille(),
            self::loges(),
        ]);
    }

    private static function corbeille(): ZoneSpec
    {
        return new ZoneSpec(
            'CORBEILLE',
            'Corbeille',
            ZoneKind::SEATED,
            new Area(150, 470, 700, 200),
            colorToken: 'sepia-600',
            rowLabels: LayoutGenerator::rowRange('M', 'R'),
            seatsPerRow: 20,
            aisleAfter: 10,
            curve: 12,
            // Technical desk at the back of the hall, in both configurations.
            blockedSeats: ['R-10', 'R-11'],
        );
    }

    private static function loges(): ZoneSpec
    {
        return new ZoneSpec(
            'LOGES',
            'Loges',
            ZoneKind::SEATED,
            new Area(28, 120, 96, 520),
            colorToken: 'olive-600',
            rowLabels: LayoutGenerator::rowRange('1', '8'),
            seatsPerRow: 5,
            arrangement: SeatArrangement::BOXES,
        );
    }
}
