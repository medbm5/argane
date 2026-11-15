<?php

declare(strict_types=1);

namespace App\Tests\Functional\Venue;

use App\Venue\Application\Dto\Area;
use App\Venue\Application\Dto\LayoutSpec;
use App\Venue\Application\Dto\ZoneSpec;
use App\Venue\Application\LayoutGenerator;
use App\Venue\Application\LayoutService;
use App\Venue\Domain\Model\Venue;
use App\Venue\Domain\Model\ZoneKind;
use App\Venue\Infrastructure\DoctrineVenueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class LayoutPersistenceTest extends KernelTestCase
{
    public function testPersistsAGeneratedLayoutAndItsClone(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $clock = new MockClock('2026-11-15 10:00:00');
        $venues = new DoctrineVenueRepository($em);
        $generator = new LayoutGenerator($clock);
        $service = new LayoutService($venues, $clock);

        $venue = new Venue('Théâtre Argane', 'theatre-argane-test', 'Agadir', 'Boulevard du 20 Août');
        $venues->save($venue);
        $layout = $generator->generate($venue, new LayoutSpec('Concert', [
            new ZoneSpec('FOSSE', 'Fosse debout', ZoneKind::STANDING, new Area(150, 110, 700, 300), capacity: 300),
            new ZoneSpec('CORB', 'Corbeille', ZoneKind::SEATED, new Area(150, 450, 700, 160), rowLabels: LayoutGenerator::rowRange('M', 'R'), seatsPerRow: 20, blockedSeats: ['R-1']),
        ]));
        $venues->saveLayout($layout);
        $service->lock($layout->id());

        $clone = $service->clone($layout->id());
        $em->clear();

        $reloaded = $venues->findLayout($layout->id()) ?? self::fail();
        self::assertTrue($reloaded->isLocked());
        self::assertSame(300 + 119, $reloaded->capacity());

        $reloadedClone = $venues->findLayout($clone->id()) ?? self::fail();
        self::assertSame('Concert (copie)', $reloadedClone->name());
        self::assertFalse($reloadedClone->isLocked());
        self::assertSame(419, $reloadedClone->capacity());
        self::assertTrue($reloadedClone->clonedFrom()?->id()->equals($layout->id()));
    }
}
