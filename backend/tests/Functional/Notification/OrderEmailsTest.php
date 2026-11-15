<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use App\Booking\Application\CancellationService;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\User;
use App\Notification\Application\Message\OrderNotificationsHandler;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Application\Message\OrderCancelled;
use App\Shared\Application\Message\OrderPaid;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\Orders;
use App\Tests\Support\SalesFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

final class OrderEmailsTest extends KernelTestCase
{
    private OrderNotificationsHandler $handler;

    protected function setUp(): void
    {
        Clock::set(new MockClock(CatalogFactory::NOW));
        self::bootKernel();
        $this->handler = self::getContainer()->get(OrderNotificationsHandler::class);
    }

    private function buyer(string $name): User
    {
        $user = new User('user_'.$name, $name.'@example.com', ucfirst($name), Clock::get()->now());
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function lastEmail(): TemplatedEmail
    {
        $email = self::getMailerMessage([] === self::getMailerMessages() ? 0 : \count(self::getMailerMessages()) - 1);
        self::assertInstanceOf(TemplatedEmail::class, $email);

        return $email;
    }

    public function testSendsTheConfirmationWithThePdfTickets(): void
    {
        $performance = SalesFactory::onSale(self::getContainer());
        $order = Orders::paid(self::getContainer(), $performance, $this->buyer('alice'), ['A-1', 'A-2'], tariffCode: Tariff::REDUCED);

        ($this->handler->onPaid(...))(new OrderPaid($order->id()->toRfc4122()));

        self::assertEmailCount(1);
        $email = $this->lastEmail();
        self::assertEmailAddressContains($email, 'to', 'alice@example.com');
        self::assertEmailHeaderSame($email, 'subject', 'Vos billets · Le Misanthrope · '.$order->reference());
        self::assertEmailAttachmentCount($email, 1);
        self::assertSame('billets-'.$order->reference().'.pdf', $email->getAttachments()[0]->getFilename());
        self::assertStringStartsWith('%PDF-', $email->getAttachments()[0]->getBody());
        self::assertEmailHtmlBodyContains($email, 'justificatif');
        self::assertEmailHtmlBodyContains($email, 'http://localhost:5173/mes-billets/'.$order->id());
        self::assertEmailTextBodyContains($email, 'Total : 300 MAD');
    }

    public function testExplainsTheRefundWhenTheBuyerCancels(): void
    {
        $performance = SalesFactory::onSale(self::getContainer());
        $alice = $this->buyer('alice');
        $order = Orders::paid(self::getContainer(), $performance, $alice, ['A-1']);
        self::getContainer()->get(CancellationService::class)->cancelByCustomer($order->id()->toRfc4122(), $alice->id());

        ($this->handler->onCancelled(...))(new OrderCancelled($order->id()->toRfc4122(), OrderCancelled::BY_CUSTOMER));

        $email = $this->lastEmail();
        self::assertEmailHeaderSame($email, 'subject', 'Commande annulée · '.$order->reference());
        self::assertEmailHtmlBodyContains($email, 'remboursés sur votre carte');
        self::assertEmailTextBodyContains($email, '250 MAD sur votre carte');
    }

    public function testTellsCounterBuyersOfACancelledShowToComeBack(): void
    {
        $performance = SalesFactory::onSale(self::getContainer());
        $order = Orders::paid(self::getContainer(), $performance, $this->buyer('bob'), ['B-1']);
        $db = self::getContainer()->get(Connection::class);
        $db->executeStatement("UPDATE payment SET provider = 'CASH' WHERE order_id = ?", [$order->id()->toBinary()], [ParameterType::BINARY]);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear(); // forget the in-memory card payment
        $performance = $em->find(Performance::class, $performance->id()) ?? self::fail();
        $performance->cancel('Le comédien principal est souffrant.', Clock::get()->now());
        $em->flush();
        self::getContainer()->get(CancellationService::class)->cancelForPerformance($performance->id(), null);

        ($this->handler->onCancelled(...))(new OrderCancelled($order->id()->toRfc4122(), OrderCancelled::PERFORMANCE_CANCELLED, 'Le comédien principal est souffrant.'));

        $email = $this->lastEmail();
        self::assertEmailHeaderSame($email, 'subject', 'Représentation annulée · Le Misanthrope');
        self::assertEmailHtmlBodyContains($email, 'Le comédien principal est souffrant.');
        self::assertEmailHtmlBodyContains($email, 'présentez-vous au guichet');
        self::assertEmailHtmlBodyContains($email, 'Voir les autres dates');
    }

    public function testSendsNothingWithoutAnAddress(): void
    {
        $performance = SalesFactory::onSale(self::getContainer());
        $order = Orders::paid(self::getContainer(), $performance, $this->buyer('carol'), ['C-1']);
        // An anonymous walk-in counter sale: no account, no email given.
        self::getContainer()->get(Connection::class)->executeStatement('UPDATE customer_order SET customer_id = NULL WHERE id = ?', [$order->id()->toBinary()], [ParameterType::BINARY]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        ($this->handler->onPaid(...))(new OrderPaid($order->id()->toRfc4122()));

        self::assertEmailCount(0);
    }
}
