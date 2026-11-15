<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Catalog\Application\PerformancePublisher;
use App\Catalog\Domain\Model\Performance;
use App\Pricing\Domain\Model\PerformancePrice;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\SalesChannel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * An on-sale performance on the CatalogFactory hall (PARTERRE 12 seats with C-4 blocked,
 * FOSSE 50 standing), priced in both zones:
 * Plein 250 · Réduit 150 (proof) · Groupe 180 (10–30) · Invitation 0 (box office staff).
 */
final class SalesFactory
{
    public const int FULL = 25_000;
    public const int REDUCED = 15_000;
    public const int GROUP = 18_000;

    public static function onSale(ContainerInterface $container, string $startsAt = '2026-12-12 19:00:00', ?DateTimeImmutable $salesOpenAt = null): Performance
    {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class); // @phpstan-ignore symfonyContainer.privateService (callers pass the test container)
        $venue = CatalogFactory::venue();
        $layout = CatalogFactory::layout($venue);
        $event = CatalogFactory::event($venue);
        $performance = Performance::schedule($event, $layout, new DateTimeImmutable($startsAt), $salesOpenAt ?? new DateTimeImmutable('2026-11-01 09:00:00'));
        foreach ([$venue, $layout, $event, $performance] as $entity) {
            $em->persist($entity);
        }

        $both = [SalesChannel::ONLINE, SalesChannel::BOX_OFFICE];
        $tariffs = [
            [new Tariff($venue, Tariff::FULL, 'Plein', $both), self::FULL],
            [new Tariff($venue, Tariff::REDUCED, 'Réduit', $both, requiresProofAtEntry: true), self::REDUCED],
            [new Tariff($venue, Tariff::GROUP, 'Groupe', $both, minQuantity: 10, maxQuantity: 30), self::GROUP],
            [new Tariff($venue, Tariff::INVITATION, 'Invitation', [SalesChannel::BOX_OFFICE], allowedRoles: ['ROLE_BOX_OFFICE'], isFree: true), 0],
        ];
        foreach ($tariffs as [$tariff]) {
            $em->persist($tariff);
        }
        foreach ($layout->zones() as $zone) {
            foreach ($tariffs as [$tariff, $cents]) {
                $em->persist(new PerformancePrice($performance, $zone, $tariff, $cents));
            }
        }
        $em->flush();

        /** @var PerformancePublisher $publisher */
        $publisher = $container->get(PerformancePublisher::class); // @phpstan-ignore symfonyContainer.privateService (callers pass the test container)
        $publisher->publish($performance, openSales: true);

        return $performance;
    }

    /**
     * Performance seat ids by seat label ("A-1" => uuid).
     *
     * @return array<string, string>
     */
    public static function seatIds(Connection $connection, Performance $performance): array
    {
        $rows = $connection->fetchAllAssociative(
            "SELECT ps.id, CONCAT(s.row_label, '-', s.number) AS label
               FROM performance_seat ps JOIN seat s ON s.id = ps.seat_id
              WHERE ps.performance_id = ?",
            [$performance->id()->toBinary()],
            [ParameterType::BINARY],
        );
        $ids = [];
        foreach ($rows as $row) {
            $ids[(string) $row['label']] = Uuid::fromBinary((string) $row['id'])->toRfc4122();
        }

        return $ids;
    }

    public static function zoneId(Performance $performance, string $code): string
    {
        return ($performance->layout()->zoneByCode($code) ?? throw new LogicException($code))->id()->toRfc4122();
    }
}
