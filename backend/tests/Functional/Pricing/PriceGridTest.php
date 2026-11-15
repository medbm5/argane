<?php

declare(strict_types=1);

namespace App\Tests\Functional\Pricing;

use App\Catalog\Infrastructure\DoctrinePerformanceRepository;
use App\Pricing\Application\PriceGridService;
use App\Pricing\Domain\Model\PerformancePrice;
use App\Pricing\Domain\Model\Tariff;
use App\Pricing\Infrastructure\DoctrinePriceRepository;
use App\Pricing\Infrastructure\DoctrineTariffRepository;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\SalesChannel;
use App\Tests\Support\CatalogFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PriceGridTest extends KernelTestCase
{
    public function testSetsAndCopiesAGridAcrossPerformancesByZoneCode(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $prices = new DoctrinePriceRepository($em);
        $service = new PriceGridService($prices, new DoctrineTariffRepository($em), new DoctrinePerformanceRepository($em));

        [$venue, $layout, $event, $first] = CatalogFactory::persisted($em);
        $otherLayout = $layout->cloneAs('Copie', new DateTimeImmutable(CatalogFactory::NOW));
        $second = CatalogFactory::performance($event, $otherLayout, '2026-12-13 19:00:00');
        $full = new Tariff($venue, 'FULL', 'Plein', [SalesChannel::ONLINE, SalesChannel::BOX_OFFICE]);
        $invitation = new Tariff($venue, 'INVITATION', 'Invitation', [SalesChannel::BOX_OFFICE], isFree: true);
        foreach ([$otherLayout, $second, $full, $invitation] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $parterre = $layout->zoneByCode('PARTERRE') ?? self::fail();
        $fosse = $layout->zoneByCode('FOSSE') ?? self::fail();
        $service->setGrid($first, [
            ['zoneId' => $parterre->id()->toRfc4122(), 'tariffId' => $full->id()->toRfc4122(), 'amountCents' => 25_000],
            ['zoneId' => $parterre->id()->toRfc4122(), 'tariffId' => $invitation->id()->toRfc4122(), 'amountCents' => 0],
            ['zoneId' => $fosse->id()->toRfc4122(), 'tariffId' => $full->id()->toRfc4122(), 'amountCents' => 15_000],
            ['zoneId' => $fosse->id()->toRfc4122(), 'tariffId' => $invitation->id()->toRfc4122(), 'amountCents' => null],
        ]);

        self::assertSame(1, $service->applyToAll($first));
        $em->clear();

        $copied = $prices->ofPerformance($em->find($second::class, $second->id()) ?? self::fail());
        $summary = array_map(static fn (PerformancePrice $p): string => $p->zone()->code().'/'.$p->tariff()->code().'='.$p->amountCents(), $copied);
        sort($summary);
        self::assertSame(['FOSSE/FULL=15000', 'PARTERRE/FULL=25000', 'PARTERRE/INVITATION=0'], $summary);
        self::assertTrue($copied[0]->zone()->layout()->id()->equals($otherLayout->id()), 'prices point at the second layout’s zones');
    }

    public function testRejectsZonesFromAnotherLayoutAndPricedInvitations(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $service = new PriceGridService(new DoctrinePriceRepository($em), new DoctrineTariffRepository($em), new DoctrinePerformanceRepository($em));
        [$venue, , , $performance] = CatalogFactory::persisted($em);
        [, $foreignLayout] = CatalogFactory::persisted($em);
        $invitation = new Tariff($venue, 'INVITATION', 'Invitation', [SalesChannel::BOX_OFFICE], isFree: true);
        $em->persist($invitation);
        $em->flush();

        $codes = [];
        foreach ([
            ['zoneId' => ($foreignLayout->zoneByCode('PARTERRE') ?? self::fail())->id()->toRfc4122(), 'tariffId' => $invitation->id()->toRfc4122(), 'amountCents' => 0],
            ['zoneId' => ($performance->layout()->zoneByCode('PARTERRE') ?? self::fail())->id()->toRfc4122(), 'tariffId' => $invitation->id()->toRfc4122(), 'amountCents' => 500],
        ] as $cell) {
            try {
                $service->setGrid($performance, [$cell]);
            } catch (BusinessRuleException $e) {
                $codes[] = $e->errorCode();
            }
        }

        self::assertSame(['PRICE_ZONE_MISMATCH', 'PRICE_FREE_TARIFF'], $codes);
    }
}
