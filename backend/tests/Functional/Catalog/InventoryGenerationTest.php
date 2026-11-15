<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Catalog\Application\PerformancePublisher;
use App\Catalog\Domain\Model\PerformanceStatus;
use App\Catalog\Infrastructure\DbalInventoryRepository;
use App\Catalog\Infrastructure\DoctrinePerformanceRepository;
use App\Shared\Domain\Exception\ConflictException;
use App\Tests\Support\CatalogFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class InventoryGenerationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $db;
    private PerformancePublisher $publisher;
    private DbalInventoryRepository $inventory;

    protected function setUp(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->db = $this->em->getConnection();
        $this->inventory = new DbalInventoryRepository($this->db);
        $this->publisher = new PerformancePublisher(new DoctrinePerformanceRepository($this->em), $this->inventory, $this->em, new MockClock(CatalogFactory::NOW));
    }

    public function testPublishingCreatesOneRowPerSeatAndStandingZone(): void
    {
        [, , , $performance] = CatalogFactory::persisted($this->em);

        $this->publisher->publish($performance, openSales: true);

        self::assertTrue($performance->isPublished());
        self::assertSame(PerformanceStatus::ON_SALE, $performance->status());
        self::assertSame(['seats' => 12, 'standingZones' => 1], $this->inventory->count($performance));

        /** @var array<string, int|string> $byStatus */
        $byStatus = $this->db->fetchAllKeyValue(
            'SELECT status, COUNT(*) FROM performance_seat WHERE performance_id = ? GROUP BY status',
            [$performance->id()->toBinary()],
        );
        self::assertEquals(['AVAILABLE' => 11, 'BLOCKED' => 1], $byStatus);

        $stock = $this->db->fetchAssociative('SELECT capacity, sold_count FROM performance_zone_stock WHERE performance_id = ?', [$performance->id()->toBinary()]);
        self::assertEquals(['capacity' => 50, 'sold_count' => 0], $stock);
    }

    public function testRepublishingIsHarmless(): void
    {
        [, , , $performance] = CatalogFactory::persisted($this->em);

        $this->publisher->publish($performance);
        $this->publisher->publish($performance);

        self::assertSame(['seats' => 12, 'standingZones' => 1], $this->inventory->count($performance));
    }

    public function testRequiresAPublishedEvent(): void
    {
        [, , , $performance] = CatalogFactory::persisted($this->em, eventPublished: false);

        $this->expectExceptionObject(ConflictException::because('EVENT_NOT_PUBLISHED', 'Publiez d’abord le spectacle.'));
        $this->publisher->publish($performance);
    }

    public function testRefusesAPastPerformance(): void
    {
        [, , , $performance] = CatalogFactory::persisted($this->em, startsAt: '2026-11-01 19:00:00');

        try {
            $this->publisher->publish($performance);
            self::fail('Expected a conflict');
        } catch (ConflictException $e) {
            self::assertSame('PERFORMANCE_PAST', $e->errorCode());
        }
        self::assertSame(['seats' => 0, 'standingZones' => 0], $this->inventory->count($performance));
    }
}
