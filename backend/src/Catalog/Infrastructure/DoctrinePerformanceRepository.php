<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure;

use App\Catalog\Domain\Model\Event;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\PerformanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePerformanceRepository implements PerformanceRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Performance $performance): void
    {
        $this->em->persist($performance);
        $this->em->flush();
    }

    public function find(Uuid $id): ?Performance
    {
        return $this->em->find(Performance::class, $id);
    }

    public function ofEvent(Event $event): array
    {
        /** @var list<Performance> */
        return $this->em->getRepository(Performance::class)->findBy(['event' => $event], ['startsAt' => 'ASC']);
    }
}
