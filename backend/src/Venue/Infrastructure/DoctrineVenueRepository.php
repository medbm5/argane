<?php

declare(strict_types=1);

namespace App\Venue\Infrastructure;

use App\Venue\Domain\Model\HallLayout;
use App\Venue\Domain\Model\Venue;
use App\Venue\Domain\VenueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineVenueRepository implements VenueRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Venue $venue): void
    {
        $this->em->persist($venue);
        $this->em->flush();
    }

    public function findBySlug(string $slug): ?Venue
    {
        return $this->em->getRepository(Venue::class)->findOneBy(['slug' => $slug]);
    }

    public function main(): ?Venue
    {
        return $this->em->getRepository(Venue::class)->findOneBy([], ['slug' => 'ASC']);
    }

    public function saveLayout(HallLayout $layout): void
    {
        $this->em->persist($layout);
        $this->em->flush();
    }

    public function findLayout(Uuid $id): ?HallLayout
    {
        return $this->em->find(HallLayout::class, $id);
    }

    public function layoutsOf(Venue $venue, bool $includeArchived = false): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('l', 'z')
            ->from(HallLayout::class, 'l')
            ->leftJoin('l.zones', 'z')
            ->where('l.venue = :venue')
            ->setParameter('venue', $venue->id(), 'uuid')
            ->orderBy('l.isDefault', 'DESC')
            ->addOrderBy('l.createdAt', 'ASC');
        if (!$includeArchived) {
            $qb->andWhere('l.archivedAt IS NULL');
        }

        /** @var list<HallLayout> */
        return $qb->getQuery()->getResult();
    }
}
