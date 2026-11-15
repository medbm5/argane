<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Model\Event;
use App\Catalog\Domain\Model\EventCategory;
use App\Catalog\Domain\Model\EventStatus;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\Model\PerformanceStatus;
use App\Shared\Domain\Exception\DomainException;
use App\Tests\Support\CatalogFactory;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PublicationRulesTest extends TestCase
{
    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    private static function assertCode(string $code, callable $action): void
    {
        try {
            $action();
            self::fail(sprintf('Expected %s', $code));
        } catch (DomainException $e) {
            self::assertSame($code, $e->errorCode());
        }
    }

    public function testSlugsAreAsciiAndLowercase(): void
    {
        self::assertSame('l-atlantique-memoire-des-marins', Event::slugify('L’Atlantique, mémoire des marins'));
        self::assertSame('rire-a-l-ouest', Event::slugify("Rire à l'ouest"));
    }

    public function testAnEventNeedsADescriptionToBePublished(): void
    {
        $venue = CatalogFactory::venue();
        $event = new Event($venue, 'Cyrano', EventCategory::THEATRE, 150, null, self::at('2026-11-01'));

        self::assertCode('EVENT_INCOMPLETE', static fn () => $event->publish(self::at('2026-11-01')));

        $event->describe('Cyrano de Bergerac', 'Rostand', EventCategory::THEATRE, 'Panache.', 150, 10, null);
        $event->publish(self::at('2026-11-02'));
        $event->publish(self::at('2026-11-03'));

        self::assertSame(EventStatus::PUBLISHED, $event->status());
        self::assertEquals(self::at('2026-11-02'), $event->publishedAt(), 'first publication date is kept');
    }

    public function testAnArchivedEventCannotBePublished(): void
    {
        $event = CatalogFactory::event(CatalogFactory::venue());
        $event->archive();

        self::assertCode('EVENT_ARCHIVED', static fn () => $event->publish(self::at('2026-11-01')));
    }

    public function testRejectsUnrealisticDurations(): void
    {
        self::assertCode('EVENT_INVALID_DURATION', static fn () => new Event(CatalogFactory::venue(), 'X', EventCategory::DANCE, 2, null, self::at('2026-11-01')));
    }

    public function testValidatesTheSchedule(): void
    {
        $venue = CatalogFactory::venue();
        $event = CatalogFactory::event($venue);
        $layout = CatalogFactory::layout($venue);
        $start = self::at('2026-12-12 19:00');

        self::assertCode('PERFORMANCE_INVALID_SCHEDULE', static fn () => new Performance($event, $layout, $start, $start->modify('+1 minute'), self::at('2026-11-01'), $start));
        self::assertCode('PERFORMANCE_INVALID_SCHEDULE', static fn () => new Performance($event, $layout, $start, $start, $start, $start->modify('-1 day')));
        self::assertCode('PERFORMANCE_INVALID_SCHEDULE', static fn () => new Performance($event, $layout, $start, $start, self::at('2026-11-01'), $start->modify('+2 hours')));
    }

    public function testRejectsALayoutFromAnotherVenue(): void
    {
        $event = CatalogFactory::event(CatalogFactory::venue());
        $foreignLayout = CatalogFactory::layout(CatalogFactory::venue('other'));

        self::assertCode('PERFORMANCE_LAYOUT_MISMATCH', static fn () => CatalogFactory::performance($event, $foreignLayout));
    }

    public function testSalesOpenOnlyOncePublishedAndBeforeCurtain(): void
    {
        $venue = CatalogFactory::venue();
        $performance = CatalogFactory::performance(CatalogFactory::event($venue), CatalogFactory::layout($venue));

        self::assertCode('PERFORMANCE_NOT_PUBLISHED', static fn () => $performance->openSales(self::at('2026-11-15')));

        $performance->markPublished(self::at('2026-11-15'));
        $performance->openSales(self::at('2026-11-15'));

        self::assertSame(PerformanceStatus::ON_SALE, $performance->status());
        self::assertTrue($performance->isOnSale(self::at('2026-11-15')));
        self::assertFalse($performance->isOnSale(self::at('2026-10-30')), 'before the sales window');
        self::assertFalse($performance->isOnSale(self::at('2026-12-12 19:00')), 'at curtain time');
        self::assertSame('PAST', $performance->displayStatus(self::at('2026-12-13')));

        $performance->closeSales();
        self::assertFalse($performance->isOnSale(self::at('2026-11-15')));
    }

    public function testCancellationIsFinal(): void
    {
        $venue = CatalogFactory::venue();
        $performance = CatalogFactory::performance(CatalogFactory::event($venue), CatalogFactory::layout($venue));
        $performance->markPublished(self::at('2026-11-15'));
        $performance->cancel('Artiste souffrant', self::at('2026-11-20'));
        $performance->cancel('Again', self::at('2026-11-21'));

        self::assertTrue($performance->isCancelled());
        self::assertSame('Artiste souffrant', $performance->cancelReason());
        self::assertSame('CANCELLED', $performance->displayStatus(self::at('2026-12-20')));
        self::assertCode('PERFORMANCE_CANCELLED', static fn () => $performance->openSales(self::at('2026-11-22')));
    }

    public function testThePerformanceEndsAfterTheEventDuration(): void
    {
        $venue = CatalogFactory::venue();
        $performance = CatalogFactory::performance(CatalogFactory::event($venue), CatalogFactory::layout($venue));

        self::assertEquals(self::at('2026-12-12 21:00'), $performance->endsAt());
        self::assertEquals(self::at('2026-12-12 18:30'), $performance->doorsAt());
    }
}
