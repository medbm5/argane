<?php

declare(strict_types=1);

namespace App\Booking\Domain;

use App\Booking\Domain\Model\Order;
use Symfony\Component\Uid\Uuid;

interface OrderRepository
{
    public function save(Order $order): void;

    public function find(Uuid $id): ?Order;

    public function findByReference(string $reference): ?Order;
}
