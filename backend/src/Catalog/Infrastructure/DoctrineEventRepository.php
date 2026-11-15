<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure;

use App\Catalog\Domain\EventRepository;
use App\Catalog\Domain\Model\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineEventRepository implements EventRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Event $event): void
    {
        $this->em->persist($event);
        $this->em->flush();
    }

    public function find(Uuid $id): ?Event
    {
        return $this->em->find(Event::class, $id);
    }

    public function findBySlug(string $slug): ?Event
    {
        return $this->em->getRepository(Event::class)->findOneBy(['slug' => $slug]);
    }

    public function slugExists(string $slug): bool
    {
        return null !== $this->findBySlug($slug);
    }
}
