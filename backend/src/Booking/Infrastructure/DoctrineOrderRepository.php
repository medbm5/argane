<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure;

use App\Booking\Domain\Model\Hold;
use App\Booking\Domain\Model\Order;
use App\Booking\Domain\Model\OrderStatus;
use App\Booking\Domain\OrderRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Order $order): void
    {
        $this->em->persist($order);
        $this->em->flush();
    }

    public function find(Uuid $id): ?Order
    {
        return $this->em->find(Order::class, $id);
    }

    public function findByReference(string $reference): ?Order
    {
        return $this->em->getRepository(Order::class)->findOneBy(['reference' => strtoupper($reference)]);
    }

    public function unpaidOfHold(Hold $hold): array
    {
        /** @var list<Order> */
        return $this->em->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->where('o.hold = :hold AND o.status IN (:unpaid)')
            ->setParameter('hold', $hold->id(), 'uuid')
            ->setParameter('unpaid', [OrderStatus::PENDING_PAYMENT->value, OrderStatus::FAILED->value])
            ->getQuery()
            ->getResult();
    }

    public function expireUnpaid(DateTimeImmutable $now): int
    {
        return (int) $this->em->getConnection()->executeStatement(
            "UPDATE customer_order o JOIN hold h ON h.id = o.hold_id
                SET o.status = 'EXPIRED'
              WHERE o.status IN ('PENDING_PAYMENT', 'FAILED') AND h.expires_at <= ?",
            [$now->format('Y-m-d H:i:s')],
        );
    }
}
