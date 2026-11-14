<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Converts UTC instants to the venue's wall-clock time (Africa/Casablanca) for display,
 * and venue wall-clock input back to UTC for storage.
 */
final class VenueTime
{
    public const string DEFAULT_TIMEZONE = 'Africa/Casablanca';

    public static function toVenue(DateTimeImmutable $instant, string $timezone = self::DEFAULT_TIMEZONE): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone($timezone));
    }

    public static function fromVenueLocal(string $localDateTime, string $timezone = self::DEFAULT_TIMEZONE): DateTimeImmutable
    {
        $local = new DateTimeImmutable($localDateTime, new DateTimeZone($timezone));

        return $local->setTimezone(new DateTimeZone('UTC'));
    }

    public static function toUtc(DateTimeImmutable $instant): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone('UTC'));
    }

    /** ISO 8601 with the venue offset, e.g. 2026-12-05T20:30:00+01:00 */
    public static function format(DateTimeImmutable $instant, string $timezone = self::DEFAULT_TIMEZONE): string
    {
        return self::toVenue($instant, $timezone)->format(\DATE_ATOM);
    }
}
