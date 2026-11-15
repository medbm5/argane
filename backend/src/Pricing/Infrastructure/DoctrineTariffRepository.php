<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure;

use App\Pricing\Domain\Model\Tariff;
use App\Pricing\Domain\TariffRepository;
use App\Venue\Domain\Model\Venue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineTariffRepository implements TariffRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Tariff $tariff): void
    {
        $this->em->persist($tariff);
        $this->em->flush();
    }

    public function find(Uuid $id): ?Tariff
    {
        return $this->em->find(Tariff::class, $id);
    }

    public function findByCode(Venue $venue, string $code): ?Tariff
    {
        return $this->em->getRepository(Tariff::class)->findOneBy(['venue' => $venue, 'code' => strtoupper($code)]);
    }

    public function ofVenue(Venue $venue, bool $activeOnly = true): array
    {
        $criteria = ['venue' => $venue];
        if ($activeOnly) {
            $criteria['active'] = true;
        }

        /** @var list<Tariff> */
        return $this->em->getRepository(Tariff::class)->findBy($criteria, ['sortOrder' => 'ASC', 'label' => 'ASC']);
    }
}
