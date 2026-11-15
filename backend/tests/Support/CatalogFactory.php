<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Catalog\Domain\Model\Event;
use App\Catalog\Domain\Model\EventCategory;
use App\Catalog\Domain\Model\Performance;
use App\Venue\Application\Dto\Area;
use App\Venue\Application\Dto\LayoutSpec;
use App\Venue\Application\Dto\ZoneSpec;
use App\Venue\Application\LayoutGenerator;
use App\Venue\Domain\Model\HallLayout;
use App\Venue\Domain\Model\Venue;
use App\Venue\Domain\Model\ZoneKind;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;

/**
 * Small, fast catalog graphs for tests: a 12-seat "PARTERRE" (A-1..C-4, C-4 blocked)
 * and a 50-place standing "FOSSE".
 */
final class CatalogFactory
{
    public const string NOW = '2026-11-15 10:00:00';

    public static function venue(string $slug = 'argane-test'): Venue
    {
        return new Venue('Théâtre Argane', $slug.'-'.bin2hex(random_bytes(3)), 'Agadir', 'Boulevard du 20 Août');
    }

    public static function layout(Venue $venue, bool $withStanding = true): HallLayout
    {
        $zones = [
            new ZoneSpec('PARTERRE', 'Parterre', ZoneKind::SEATED, new Area(100, 100, 400, 200), rowLabels: ['A', 'B', 'C'], seatsPerRow: 4, accessibleSeats: ['A-1'], blockedSeats: ['C-4']),
        ];
        if ($withStanding) {
            $zones[] = new ZoneSpec('FOSSE', 'Fosse', ZoneKind::STANDING, new Area(550, 100, 300, 200), capacity: 50);
        }

        return (new LayoutGenerator(new MockClock(self::NOW)))->generate($venue, new LayoutSpec('Test', $zones));
    }

    public static function event(Venue $venue, bool $published = true, string $title = 'Le Misanthrope'): Event
    {
        $event = new Event($venue, $title, EventCategory::THEATRE, 120, null, new DateTimeImmutable(self::NOW), Event::slugify($title).'-'.bin2hex(random_bytes(3)));
        $event->describe($title, null, EventCategory::THEATRE, 'Une comédie de Molière.', 120, null, null);
        if ($published) {
            $event->publish(new DateTimeImmutable(self::NOW));
        }

        return $event;
    }

    public static function performance(Event $event, HallLayout $layout, string $startsAt = '2026-12-12 19:00:00'): Performance
    {
        return Performance::schedule($event, $layout, new DateTimeImmutable($startsAt), new DateTimeImmutable('2026-11-01 09:00:00'));
    }

    /**
     * @return array{Venue, HallLayout, Event, Performance}
     */
    public static function persisted(EntityManagerInterface $em, bool $eventPublished = true, string $startsAt = '2026-12-12 19:00:00'): array
    {
        $venue = self::venue();
        $layout = self::layout($venue);
        $event = self::event($venue, $eventPublished);
        $performance = self::performance($event, $layout, $startsAt);
        foreach ([$venue, $layout, $event, $performance] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return [$venue, $layout, $event, $performance];
    }
}
