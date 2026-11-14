<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\VenueTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class VenueTimeTest extends TestCase
{
    public function testConvertsUtcToCasablanca(): void
    {
        $utc = new DateTimeImmutable('2026-12-05 19:30:00', new DateTimeZone('UTC'));

        self::assertSame('2026-12-05T20:30:00+01:00', VenueTime::format($utc));
    }

    public function testConvertsVenueLocalInputToUtc(): void
    {
        $utc = VenueTime::fromVenueLocal('2026-12-05 20:30');

        self::assertSame('UTC', $utc->getTimezone()->getName());
        self::assertSame('2026-12-05 19:30', $utc->format('Y-m-d H:i'));
    }

    public function testMockClockIsUsableForDeterministicTests(): void
    {
        $clock = new MockClock('2026-11-14 10:00:00 UTC');
        $clock->modify('+10 minutes');

        self::assertSame('10:10', $clock->now()->format('H:i'));
    }
}
