<?php

declare(strict_types=1);

namespace App\Notification\Application\Message;

use App\Notification\Application\OrderMailer;
use App\Shared\Application\Message\OrderCancelled;
use App\Shared\Application\Message\OrderPaid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Runs on the async worker; a mail server outage is retried by Messenger. */
final readonly class OrderNotificationsHandler
{
    public function __construct(private OrderMailer $mailer)
    {
    }

    #[AsMessageHandler]
    public function onPaid(OrderPaid $message): void
    {
        $this->mailer->confirmation($message->orderId);
    }

    #[AsMessageHandler]
    public function onCancelled(OrderCancelled $message): void
    {
        $this->mailer->cancellation($message->orderId, $message->cause, $message->reason);
    }
}
