<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure;

use App\Catalog\Domain\Model\Performance;
use App\Pricing\Domain\Model\PerformancePrice;
use App\Pricing\Domain\PriceRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePriceRepository implements PriceRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function ofPerformance(Performance $performance): array
    {
        /** @var list<PerformancePrice> */
        return $this->em->createQueryBuilder()
            ->select('p', 't', 'z')
            ->from(PerformancePrice::class, 'p')
            ->join('p.tariff', 't')
            ->join('p.zone', 'z')
            ->where('p.performance = :performance')
            ->setParameter('performance', $performance->id(), 'uuid')
            ->orderBy('z.sortOrder', 'ASC')
            ->addOrderBy('t.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function replaceGrid(Performance $performance, array $prices): void
    {
        $this->em->wrapInTransaction(function () use ($performance, $prices): void {
            $this->em->createQueryBuilder()
                ->delete(PerformancePrice::class, 'p')
                ->where('p.performance = :performance')
                ->setParameter('performance', $performance->id(), 'uuid')
                ->getQuery()
                ->execute();
            foreach ($prices as $price) {
                $this->em->persist($price);
            }
            $this->em->flush();
        });
    }
}
