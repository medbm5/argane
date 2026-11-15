<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure;

use App\Booking\Domain\Model\Order;
use App\Booking\Domain\OrderRepository;
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
}
