<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Booking\Domain\Model\Order;
use App\Booking\Domain\Model\OrderStatus;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\User;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Model\Refund;
use App\Shared\Domain\SalesChannel;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;

/**
 * Story beats for the demo:
 *  - "Rire à l'ouest" (first night) was cancelled three days ago: online orders refunded
 *    through Stripe, counter orders waiting for a refund at the box office, invitations voided;
 *  - "Tamra" (first night) is almost sold out (see SalesFixtures::NEAR_SOLD_OUT).
 */
final class ScenarioFixtures extends Fixture implements DependentFixtureInterface
{
    public const string CANCELLED = 'rire-1';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getDependencies(): array
    {
        return [SalesFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $performance = $this->getReference(CatalogFixtures::PERFORMANCE_PREFIX.self::CANCELLED, Performance::class);
        $organizer = $this->getReference(UserFixtures::ORGANIZER, User::class);
        $cancelledAt = $this->clock->now()->modify('-3 days');

        $performance->cancel('Un des humoristes est souffrant. La date est annulée et remboursée.', $cancelledAt);

        /** @var list<Order> $orders */
        $orders = $this->em->getRepository(Order::class)->findBy(['performance' => $performance, 'status' => OrderStatus::PAID]);
        foreach ($orders as $order) {
            /** @var ?Payment $payment */
            $payment = $this->em->getRepository(Payment::class)->findOneBy(['order' => $order]);
            foreach ($order->tickets() as $ticket) {
                SalesChannel::ONLINE === $order->channel() ? $ticket->markRefunded() : $ticket->cancel();
            }

            if (0 === $order->totalCents()) {
                $order->cancel($cancelledAt);
                continue;
            }

            $refund = new Refund($order, $payment, $order->totalCents(), 'Représentation annulée', $organizer->id(), $cancelledAt);
            if (SalesChannel::ONLINE === $order->channel()) {
                $refund->succeed('re_fixture_'.substr($order->reference(), -6), $cancelledAt->modify(sprintf('+%d minutes', random_int(2, 90))));
                $order->markRefunded($cancelledAt);
            } else {
                $refund->awaitCounter();
                $order->cancel($cancelledAt);
            }
            $manager->persist($refund);
        }
        $manager->flush();

        // QR codes stop validating; seats go back to the (closed) inventory.
        $this->em->getConnection()->executeStatement(
            "UPDATE performance_seat SET status = 'AVAILABLE', ticket_id = NULL WHERE performance_id = ? AND status = 'SOLD'",
            [$performance->id()->toBinary()],
        );
        $this->em->getConnection()->executeStatement(
            'UPDATE performance_zone_stock SET sold_count = 0 WHERE performance_id = ?',
            [$performance->id()->toBinary()],
        );
    }
}
