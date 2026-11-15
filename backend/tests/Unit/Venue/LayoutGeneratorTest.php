<?php

declare(strict_types=1);

namespace App\Tests\Unit\Venue;

use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\ConflictException;
use App\Venue\Application\Dto\Area;
use App\Venue\Application\Dto\LayoutSpec;
use App\Venue\Application\Dto\SeatArrangement;
use App\Venue\Application\Dto\ZoneSpec;
use App\Venue\Application\LayoutGenerator;
use App\Venue\Domain\Model\HallLayout;
use App\Venue\Domain\Model\Seat;
use App\Venue\Domain\Model\Venue;
use App\Venue\Domain\Model\Zone;
use App\Venue\Domain\Model\ZoneKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class LayoutGeneratorTest extends TestCase
{
    private LayoutGenerator $generator;
    private Venue $venue;

    protected function setUp(): void
    {
        $this->generator = new LayoutGenerator(new MockClock('2026-11-15 10:00:00'));
        $this->venue = new Venue('ThÃ©Ã¢tre Argane', 'theatre-argane', 'Agadir', 'Boulevard du 20 AoÃ»t');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function orchestre(array $overrides = []): ZoneSpec
    {
        return new ZoneSpec(...[
            'code' => 'ORCH',
            'name' => 'Orchestre',
            'kind' => ZoneKind::SEATED,
            'area' => new Area(150, 110, 700, 300),
            'rowLabels' => LayoutGenerator::rowRange('A', 'L'),
            'seatsPerRow' => 20,
            'aisleAfter' => 10,
            'curve' => 20,
            'accessibleSeats' => ['A-1', 'A-2', 'A-19', 'A-20'],
            'blockedSeats' => ['L-10', 'L-11'],
            ...$overrides,
        ]);
    }

    private function theatre(): HallLayout
    {
        return $this->generator->generate($this->venue, new LayoutSpec('Configuration thÃ©Ã¢tre', [
            $this->orchestre(),
            new ZoneSpec('CORB', 'Corbeille', ZoneKind::SEATED, new Area(150, 450, 700, 160), rowLabels: LayoutGenerator::rowRange('M', 'R'), seatsPerRow: 20),
            new ZoneSpec('LOGES', 'Loges', ZoneKind::SEATED, new Area(30, 120, 90, 420), rowLabels: LayoutGenerator::rowRange('1', '8'), seatsPerRow: 5, arrangement: SeatArrangement::BOXES),
        ], isDefault: true));
    }

    /**
     * @return array<string, Seat>
     */
    private static function seatsByLabel(Zone $zone): array
    {
        $map = [];
        foreach ($zone->seats() as $seat) {
            $map[$seat->label()] = $seat;
        }

        return $map;
    }

    private function assertRuleViolated(string $code, callable $action): void
    {
        try {
            $action();
            self::fail(sprintf('Expected %s', $code));
        } catch (BusinessRuleException $e) {
            self::assertSame($code, $e->errorCode());
        }
    }

    public function testBuildsTheFourHundredSeatTheatreConfiguration(): void
    {
        $layout = $this->theatre();

        self::assertTrue($layout->isDefault());
        self::assertSame(['ORCH', 'CORB', 'LOGES'], array_map(static fn (Zone $z): string => $z->code(), $layout->zones()));
        self::assertCount(240, $layout->zoneByCode('ORCH')?->seats() ?? []);
        self::assertCount(120, $layout->zoneByCode('CORB')?->seats() ?? []);
        self::assertCount(40, $layout->zoneByCode('LOGES')?->seats() ?? []);
        self::assertSame(398, $layout->capacity(), '400 seats minus the two blocked for the technical desk');
    }

    public function testLabelsRowsAndSeatsUniquely(): void
    {
        $seats = self::seatsByLabel($this->theatre()->zoneByCode('ORCH') ?? self::fail());

        self::assertCount(240, $seats);
        self::assertArrayHasKey('A-1', $seats);
        self::assertArrayHasKey('L-20', $seats);
        self::assertArrayNotHasKey('A-21', $seats);
        self::assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'], LayoutGenerator::rowRange('A', 'L'));
        self::assertNotContains('I', LayoutGenerator::rowRange('A', 'L', skipConfusable: true));
        self::assertSame(['1', '2', '3'], LayoutGenerator::rowRange('1', '3'));
    }

    public function testFlagsAccessibleAndBlockedSeats(): void
    {
        $seats = self::seatsByLabel($this->theatre()->zoneByCode('ORCH') ?? self::fail());

        self::assertTrue($seats['A-1']->isAccessible());
        self::assertTrue($seats['A-20']->isAccessible());
        self::assertFalse($seats['A-3']->isAccessible());
        self::assertTrue($seats['L-10']->isBlocked());
        self::assertFalse($seats['L-9']->isBlocked());
    }

    public function testPlacesSeatsInsideTheZoneLeftToRightFrontToBack(): void
    {
        $seats = self::seatsByLabel($this->theatre()->zoneByCode('ORCH') ?? self::fail());

        foreach ($seats as $seat) {
            self::assertGreaterThanOrEqual(150, $seat->x());
            self::assertLessThanOrEqual(850, $seat->x());
            self::assertGreaterThanOrEqual(110, $seat->y());
            self::assertLessThanOrEqual(410, $seat->y());
        }
        self::assertLessThan($seats['A-2']->x(), $seats['A-1']->x());
        self::assertLessThan($seats['B-1']->y(), $seats['A-1']->y(), 'row A is nearest the stage');
        // Centre aisle: the gap between 10 and 11 is wider than between 9 and 10.
        self::assertGreaterThan($seats['A-10']->x() - $seats['A-9']->x(), $seats['A-11']->x() - $seats['A-10']->x());
        // Rows bow towards the stage: centre seats sit lower than the ends of the same row.
        self::assertGreaterThan($seats['A-1']->y(), $seats['A-10']->y());
    }

    public function testSplitsBoxesBetweenBothWalls(): void
    {
        $seats = self::seatsByLabel($this->theatre()->zoneByCode('LOGES') ?? self::fail());

        self::assertLessThan(150, $seats['1-1']->x(), 'box 1 on the left wall');
        self::assertGreaterThan(850, $seats['2-1']->x(), 'box 2 on the right wall');
        self::assertCount(5, array_filter($seats, static fn (Seat $s): bool => '8' === $s->rowLabel()));
    }

    public function testBuildsAStandingPitWithCapacityAndNoSeats(): void
    {
        $layout = $this->generator->generate($this->venue, new LayoutSpec('Configuration concert', [
            new ZoneSpec('FOSSE', 'Fosse debout', ZoneKind::STANDING, new Area(150, 110, 700, 300), colorToken: 'bordeaux-700', curve: 16, capacity: 300),
        ]));

        $pit = $layout->zoneByCode('FOSSE') ?? self::fail();
        self::assertTrue($pit->isStanding());
        self::assertSame(300, $pit->capacity());
        self::assertSame([], $pit->seats());
        self::assertSame(300, $layout->capacity());
        self::assertStringStartsWith('M150 126 Q500 94 850 126', $pit->shape());
    }

    public function testRejectsAStandingZoneWithoutCapacity(): void
    {
        $this->assertRuleViolated('LAYOUT_STANDING_CAPACITY', fn () => $this->generator->generate($this->venue, new LayoutSpec('X', [
            new ZoneSpec('FOSSE', 'Fosse', ZoneKind::STANDING, new Area(100, 100, 100, 100)),
        ])));
    }

    public function testRejectsUnknownSeatReferences(): void
    {
        try {
            $this->generator->generate($this->venue, new LayoutSpec('X', [$this->orchestre(['blockedSeats' => ['Z-99']])]));
            self::fail('Expected an exception');
        } catch (BusinessRuleException $e) {
            self::assertSame('LAYOUT_UNKNOWN_SEAT', $e->errorCode());
            self::assertSame(['Z-99'], $e->context()['seats']);
        }
    }

    public function testRejectsZonesOutsideThePlanAndDuplicateCodes(): void
    {
        try {
            $this->generator->generate($this->venue, new LayoutSpec('X', [$this->orchestre(['area' => new Area(900, 600, 300, 300)])]));
            self::fail('Expected an exception');
        } catch (BusinessRuleException $e) {
            self::assertSame('LAYOUT_ZONE_OUT_OF_BOUNDS', $e->errorCode());
        }

        $this->assertRuleViolated('LAYOUT_DUPLICATE_ZONE', fn () => $this->generator->generate($this->venue, new LayoutSpec('X', [$this->orchestre(), $this->orchestre()])));
    }

    public function testALockedLayoutRefusesGeometryChanges(): void
    {
        $layout = $this->theatre();
        $layout->lock(new DateTimeImmutable('2026-11-20'));
        $layout->lock(new DateTimeImmutable('2026-12-01'));

        self::assertTrue($layout->isLocked());
        self::assertEquals(new DateTimeImmutable('2026-11-20'), $layout->lockedAt(), 'locking is idempotent');

        $this->expectException(ConflictException::class);
        new Zone($layout, 'NEW', 'Nouvelle zone', ZoneKind::STANDING, 'gilt-400', 9, 'M0 0 Z', 10);
    }

    public function testALockedLayoutRefusesNewSeats(): void
    {
        $layout = $this->theatre();
        $layout->lock(new DateTimeImmutable('2026-11-20'));

        try {
            new Seat($layout->zoneByCode('ORCH') ?? self::fail(), 'Z', 1, 10, 10);
            self::fail('Expected an exception');
        } catch (ConflictException $e) {
            self::assertSame('LAYOUT_LOCKED', $e->errorCode());
        }
    }

    public function testCloningProducesAnEditableDeepCopy(): void
    {
        $source = $this->theatre();
        $source->lock(new DateTimeImmutable('2026-11-20'));

        $copy = $source->cloneAs('ThÃ©Ã¢tre (copie)', new DateTimeImmutable('2026-11-21'));

        self::assertFalse($copy->isLocked());
        self::assertFalse($copy->isDefault());
        self::assertSame($source, $copy->clonedFrom());
        self::assertSame($source->capacity(), $copy->capacity());
        $sourceOrch = $source->zoneByCode('ORCH') ?? self::fail();
        $copyOrch = $copy->zoneByCode('ORCH') ?? self::fail();
        self::assertFalse($sourceOrch->id()->equals($copyOrch->id()));
        self::assertTrue(self::seatsByLabel($copyOrch)['L-10']->isBlocked());

        new Zone($copy, 'NEW', 'Nouvelle zone', ZoneKind::STANDING, 'gilt-400', 9, 'M0 0 Z', 10);
        self::assertSame($source->capacity() + 10, $copy->capacity());
    }
}
