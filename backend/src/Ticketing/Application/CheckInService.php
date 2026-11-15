<?php

declare(strict_types=1);

namespace App\Ticketing\Application;

use App\Booking\Domain\Model\Ticket;
use App\Booking\Domain\Model\TicketStatus;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\VenueTime;
use App\Ticketing\Application\Dto\CheckInResult;
use DateTimeInterface;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Validates a scanned QR code at the door of one performance.
 *
 * Admission is a single conditional UPDATE (… WHERE status = 'VALID'): two stewards
 * scanning the same ticket at the same instant can never both let it in. Every outcome
 * is a verdict for the scanner, never an error page.
 */
final readonly class CheckInService
{
    public function __construct(
        private QrSigner $signer,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    public function scan(string $payload, string $performanceId, Uuid $stewardId): CheckInResult
    {
        try {
            $ticketId = $this->signer->ticketId($payload);
        } catch (BusinessRuleException) {
            return $this->verdict(CheckInResult::INVALID, 'QR code illisible ou étranger à Argane.', $performanceId);
        }

        $ticket = $this->em->find(Ticket::class, $ticketId);
        if (null === $ticket || !$this->signer->isAuthentic($payload, $ticket)) {
            return $this->verdict(CheckInResult::INVALID, 'Billet inconnu ou falsifié.', $performanceId);
        }

        if ($ticket->performance()->id()->toRfc4122() !== strtolower($performanceId)) {
            $start = VenueTime::toVenue($ticket->performance()->startsAt());

            return $this->verdict(CheckInResult::WRONG_PERFORMANCE, sprintf('Billet pour une autre date : %s à %s.', $start->format('d/m'), $start->format('H:i')), $performanceId, $ticket);
        }

        if (TicketStatus::CHECKED_IN === $ticket->status()) {
            return $this->verdict(CheckInResult::ALREADY_USED, sprintf('Déjà scanné à %s.', VenueTime::toVenue($ticket->checkedInAt() ?? $this->clock->now())->format('H:i')), $performanceId, $ticket);
        }
        if (TicketStatus::VALID !== $ticket->status()) {
            return $this->verdict(CheckInResult::NOT_VALID, match ($ticket->status()) {
                TicketStatus::CANCELLED => 'Billet annulé.',
                TicketStatus::REFUNDED => 'Billet remboursé.',
                default => 'Commande non réglée.',
            }, $performanceId, $ticket);
        }

        $now = $this->clock->now();
        $admitted = (int) $this->em->getConnection()->executeStatement(
            "UPDATE ticket SET status = 'CHECKED_IN', checked_in_at = ?, checked_in_by = ? WHERE id = ? AND status = 'VALID'",
            [$now->format('Y-m-d H:i:s'), $stewardId->toBinary(), $ticket->id()->toBinary()],
            [ParameterType::STRING, ParameterType::BINARY, ParameterType::BINARY],
        );
        $this->em->refresh($ticket);
        if (1 !== $admitted) {
            // Another gate got there first, a split second ago.
            return $this->verdict(CheckInResult::ALREADY_USED, 'Déjà scanné à l’instant à une autre entrée.', $performanceId, $ticket);
        }

        return $this->verdict(CheckInResult::ADMITTED, $ticket->requiresProof() ? 'Entrée validée : vérifiez le justificatif de tarif réduit.' : 'Entrée validée.', $performanceId, $ticket);
    }

    /**
     * @return array{admitted: int, expected: int}
     */
    public function counts(string $performanceId): array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            "SELECT SUM(status = 'CHECKED_IN') AS admitted, SUM(status IN ('VALID', 'CHECKED_IN')) AS expected FROM ticket WHERE performance_id = ?",
            [Uuid::isValid($performanceId) ? Uuid::fromString($performanceId)->toBinary() : ''],
            [ParameterType::BINARY],
        );

        return ['admitted' => (int) ($row['admitted'] ?? 0), 'expected' => (int) ($row['expected'] ?? 0)];
    }

    private function verdict(string $verdict, string $message, string $performanceId, ?Ticket $ticket = null): CheckInResult
    {
        $counts = $this->counts($performanceId);
        $order = $ticket?->order();
        $performance = $ticket?->performance();

        return new CheckInResult(
            $verdict,
            $message,
            $ticket?->id()->toRfc4122(),
            $order?->reference(),
            $ticket?->zoneLabel(),
            $ticket?->seatLabel(),
            $ticket?->tariffLabel(),
            $ticket?->requiresProof() ?? false,
            $order?->walkInName(),
            $ticket?->checkedInAt()?->format(DateTimeInterface::ATOM),
            CheckInResult::WRONG_PERFORMANCE === $verdict ? $performance?->startsAt()->format(DateTimeInterface::ATOM) : null,
            CheckInResult::WRONG_PERFORMANCE === $verdict ? $performance?->event()->title() : null,
            $counts['admitted'],
            $counts['expected'],
        );
    }
}
