<?php

declare(strict_types=1);

namespace App\Ticketing\Application;

use App\Booking\Domain\Model\Order;
use App\Booking\Domain\Model\Ticket;

/** Printable tickets (PDF bytes): one page per ticket, QR code included. */
interface TicketDocuments
{
    /**
     * @param list<Ticket> $tickets tickets of this order to print (all live ones by default)
     */
    public function pdf(Order $order, array $tickets): string;
}
