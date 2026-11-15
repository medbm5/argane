<?php

declare(strict_types=1);

namespace App\Ticketing\Application;

use App\Booking\Application\Dto\OrderView;
use App\Booking\Domain\Model\Order;
use App\Booking\Domain\Model\Ticket;
use App\Booking\Domain\OrderRepository;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Ticketing\Application\Dto\Printable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The buyer's wallet and printable tickets. Staff (box office and up) may print any
 * order; buyers only their own. Only live tickets (valid or already scanned) print.
 */
final readonly class TicketService
{
    public function __construct(
        private OrderRepository $orders,
        private TicketDocuments $documents,
        private QrSigner $signer,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<OrderView>
     */
    public function wallet(Uuid $customerId): array
    {
        return array_map(
            fn (Order $order): OrderView => OrderView::of($order, $this->signer->sign(...)),
            $this->orders->ticketedOf($customerId),
        );
    }

    /** All live tickets of an order, one page each. */
    public function orderPdf(string $orderId, Uuid $userId, bool $isStaff): Printable
    {
        $order = Uuid::isValid($orderId) ? $this->orders->find(Uuid::fromString($orderId)) : null;
        if (null === $order || (!$isStaff && !$order->customerId()?->equals($userId))) {
            throw NotFoundException::of('Commande', $orderId);
        }

        return $this->printable($order, $order->tickets(), 'billets-'.$order->reference().'.pdf');
    }

    public function ticketPdf(string $ticketId, Uuid $userId, bool $isStaff): Printable
    {
        $ticket = Uuid::isValid($ticketId) ? $this->em->find(Ticket::class, Uuid::fromString($ticketId)) : null;
        $order = $ticket?->order();
        if (null === $ticket || null === $order || (!$isStaff && !$order->customerId()?->equals($userId))) {
            throw NotFoundException::of('Billet', $ticketId);
        }

        return $this->printable($order, [$ticket], sprintf('billet-%s-%s.pdf', $order->reference(), $ticket->seatLabel() ?? 'debout'));
    }

    /**
     * @param list<Ticket> $tickets
     */
    private function printable(Order $order, array $tickets, string $filename): Printable
    {
        $live = array_values(array_filter($tickets, static fn (Ticket $t): bool => $t->status()->isLive()));
        if ([] === $live) {
            throw BusinessRuleException::because('NO_LIVE_TICKETS', 'Aucun billet valide à imprimer pour cette commande.', [], 409);
        }

        return new Printable($filename, $this->documents->pdf($order, $live));
    }
}
