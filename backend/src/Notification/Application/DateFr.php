<?php

declare(strict_types=1);

namespace App\Notification\Application;

use DateTimeImmutable;

/** French dates without ext-intl ("vendredi 12 décembre 2026"). */
final class DateFr
{
    private const array DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    private const array MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    public static function long(DateTimeImmutable $at): string
    {
        return sprintf('%s %d %s %s', self::DAYS[(int) $at->format('w')], (int) $at->format('j'), self::MONTHS[(int) $at->format('n') - 1], $at->format('Y'));
    }
}
