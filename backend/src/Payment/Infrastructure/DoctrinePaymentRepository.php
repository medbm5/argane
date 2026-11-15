<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure;

use App\Booking\Domain\Model\Order;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Model\Refund;
use App\Payment\Domain\PaymentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePaymentRepository implements PaymentRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Payment $payment): void
    {
        $this->em->persist($payment);
        $this->em->flush();
    }

    public function findByProviderRef(string $providerRef): ?Payment
    {
        return $this->em->getRepository(Payment::class)->findOneBy(['providerRef' => $providerRef]);
    }

    public function latestOf(Order $order): ?Payment
    {
        return $this->em->getRepository(Payment::class)->findOneBy(['order' => $order], ['createdAt' => 'DESC']);
    }

    public function saveRefund(Refund $refund): void
    {
        $this->em->persist($refund);
        $this->em->flush();
    }

    public function recordWebhook(string $eventId, string $type, DateTimeImmutable $now): bool
    {
        // Plain INSERT: the primary key makes concurrent deliveries of one event collide.
        try {
            $this->em->getConnection()->executeStatement(
                'INSERT INTO stripe_webhook_event (id, type, processed_at) VALUES (?, ?, ?)',
                [$eventId, $type, $now->format('Y-m-d H:i:s')],
            );

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    public function forgetWebhook(string $eventId): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM stripe_webhook_event WHERE id = ?', [$eventId]);
    }
}
