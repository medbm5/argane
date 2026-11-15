<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure;

use App\Booking\Domain\HoldRepository;
use App\Booking\Domain\Model\Hold;
use App\Booking\Domain\Model\HoldStatus;
use App\Catalog\Domain\Model\Performance;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineHoldRepository implements HoldRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Hold $hold): void
    {
        $this->em->persist($hold);
        $this->em->flush();
    }

    public function find(Uuid $id): ?Hold
    {
        return $this->em->find(Hold::class, $id);
    }

    public function activeOf(Uuid $userId, Performance $performance): array
    {
        /** @var list<Hold> */
        return $this->em->createQueryBuilder()
            ->select('h')
            ->from(Hold::class, 'h')
            ->where('h.userId = :user AND h.performance = :performance AND h.status = :active')
            ->setParameter('user', $userId, 'uuid')
            ->setParameter('performance', $performance->id(), 'uuid')
            ->setParameter('active', HoldStatus::ACTIVE)
            ->getQuery()
            ->getResult();
    }

    public function expireDue(DateTimeImmutable $now): int
    {
        return (int) $this->em->getConnection()->executeStatement(
            "UPDATE hold SET status = 'EXPIRED' WHERE status = 'ACTIVE' AND expires_at <= ?",
            [$now->format('Y-m-d H:i:s')],
        );
    }

    public function countCreatedSince(Uuid $userId, DateTimeImmutable $since): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM hold WHERE user_id = ? AND created_at >= ?',
            [$userId->toBinary(), $since->format('Y-m-d H:i:s')],
        );
    }
}
