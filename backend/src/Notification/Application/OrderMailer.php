<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Booking\Domain\Model\Order;
use App\Booking\Domain\OrderRepository;
use App\Identity\Domain\UserRepository;
use App\Payment\Domain\Model\RefundStatus;
use App\Payment\Domain\PaymentRepository;
use App\Shared\Application\Message\OrderCancelled;
use App\Shared\Domain\VenueTime;
use App\Ticketing\Application\TicketDocuments;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Uuid;

/**
 * Transactional emails about an order: confirmation (PDF tickets attached) and
 * cancellation (with what happens to the money). Sent to the buyer's account email, or
 * to the walk-in email given at the counter; nothing is sent when there is none.
 */
final readonly class OrderMailer
{
    public function __construct(
        private OrderRepository $orders,
        private UserRepository $users,
        private PaymentRepository $payments,
        private TicketDocuments $documents,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $from,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function confirmation(string $orderId): bool
    {
        $order = $this->order($orderId);
        $to = null === $order ? null : $this->recipient($order);
        if (null === $order || null === $to) {
            return false;
        }
        $live = array_values(array_filter($order->tickets(), static fn ($t): bool => $t->status()->isLive()));
        if ([] === $live) {
            return false;
        }

        $email = $this->email($to, sprintf('Vos billets · %s · %s', $order->performance()->event()->title(), $order->reference()))
            ->htmlTemplate('email/order_confirmation.html.twig')
            ->textTemplate('email/order_confirmation.txt.twig')
            ->context($this->context($order) + ['tickets' => $live])
            ->attach($this->documents->pdf($order, $live), 'billets-'.$order->reference().'.pdf', 'application/pdf');
        $this->mailer->send($email);

        return true;
    }

    public function cancellation(string $orderId, string $cause, ?string $reason): bool
    {
        $order = $this->order($orderId);
        $to = null === $order ? null : $this->recipient($order);
        if (null === $order || null === $to) {
            return false;
        }
        $refund = $this->payments->refundsOf($order)[0] ?? null;
        $refundState = match ($refund?->status()) {
            null => 'none',
            RefundStatus::SUCCEEDED => 'card' === $this->paidWith($order) ? 'card' : 'done',
            RefundStatus::AWAITING_COUNTER => 'counter',
            default => 'pending',
        };

        $subject = OrderCancelled::PERFORMANCE_CANCELLED === $cause
            ? sprintf('Représentation annulée · %s', $order->performance()->event()->title())
            : sprintf('Commande annulée · %s', $order->reference());
        $email = $this->email($to, $subject)
            ->htmlTemplate('email/order_cancelled.html.twig')
            ->textTemplate('email/order_cancelled.txt.twig')
            ->context($this->context($order) + [
                'cause' => $cause,
                'reason' => $reason,
                'refund_state' => $refundState,
                'refund_cents' => $refund?->amountCents() ?? 0,
            ]);
        $this->mailer->send($email);

        return true;
    }

    private function email(Address $to, string $subject): TemplatedEmail
    {
        return (new TemplatedEmail())->from(Address::create($this->from))->to($to)->subject($subject);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Order $order): array
    {
        $performance = $order->performance();
        $startsAt = VenueTime::toVenue($performance->startsAt());

        return [
            'order' => $order,
            'event' => $performance->event(),
            'date' => DateFr::long($startsAt),
            'time' => $startsAt->format('H:i'),
            'doors' => VenueTime::toVenue($performance->doorsAt())->format('H:i'),
            'venue' => $performance->event()->venue(),
            'total' => number_format($order->totalCents() / 100, $order->totalCents() % 100 ? 2 : 0, ',', ' ').' '.$order->currency(),
            'wallet_url' => rtrim($this->frontendUrl, '/').'/mes-billets/'.$order->id()->toRfc4122(),
            'event_url' => rtrim($this->frontendUrl, '/').'/spectacles/'.$performance->event()->slug(),
        ];
    }

    private function recipient(Order $order): ?Address
    {
        $customerId = $order->customerId();
        $user = null === $customerId ? null : $this->users->find($customerId);
        if (null !== $user) {
            return new Address($user->email(), $user->displayName());
        }
        $email = $order->walkInEmail();
        if (null === $email || '' === $email) {
            return null;
        }

        return new Address($email, (string) $order->walkInName());
    }

    private function paidWith(Order $order): string
    {
        return 'STRIPE' === $this->payments->latestOf($order)?->provider()->value ? 'card' : 'other';
    }

    private function order(string $id): ?Order
    {
        $order = Uuid::isValid($id) ? $this->orders->find(Uuid::fromString($id)) : null;
        if (null === $order) {
            $this->logger->warning('Notification for unknown order {order}', ['order' => $id]);
        }

        return $order;
    }
}
