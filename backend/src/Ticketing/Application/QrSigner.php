<?php

declare(strict_types=1);

namespace App\Ticketing\Application;

use App\Booking\Domain\Model\Ticket;
use App\Shared\Domain\Exception\BusinessRuleException;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * QR payload of a ticket: `ARG1.<ticket uuid, base58>.<signature>`.
 *
 * The signature is HMAC-SHA256(secret, "ARG1|<uuid>|<ticket qrNonce>"), truncated to
 * 128 bits and base64url-encoded. The payload alone proves nothing can be forged; the
 * per-ticket nonce lets a ticket be re-issued (new QR) without changing its id.
 * Check-in still looks the ticket up: status, date and performance are checked there.
 */
final readonly class QrSigner
{
    public const string VERSION = 'ARG1';

    public function __construct(
        #[Autowire('%env(default:kernel.secret:TICKET_QR_SECRET)%')]
        private string $secret,
    ) {
        if ('' === $secret) {
            throw new LogicException('Set APP_SECRET (or TICKET_QR_SECRET) to sign ticket QR codes.');
        }
    }

    public function sign(Ticket $ticket): string
    {
        $id = $ticket->id();

        return self::VERSION.'.'.$id->toBase58().'.'.$this->signature($id, $ticket->qrNonce());
    }

    /**
     * Reads the ticket id out of a payload, without trusting it yet.
     *
     * @throws BusinessRuleException QR_INVALID
     */
    public function ticketId(string $payload): Uuid
    {
        $parts = explode('.', trim($payload));
        if (3 !== \count($parts) || self::VERSION !== $parts[0]) {
            throw InvalidQr::because('Ce QR code n’est pas un billet Argane.');
        }
        try {
            return Uuid::fromBase58($parts[1]);
        } catch (InvalidArgumentException) {
            throw InvalidQr::because('Ce QR code n’est pas un billet Argane.');
        }
    }

    /** Constant-time check that the payload was issued for this ticket (current nonce). */
    public function isAuthentic(string $payload, Ticket $ticket): bool
    {
        return hash_equals($this->sign($ticket), trim($payload));
    }

    private function signature(Uuid $id, string $nonce): string
    {
        $mac = hash_hmac('sha256', self::VERSION.'|'.$id->toRfc4122().'|'.$nonce, $this->secret, true);

        return rtrim(strtr(base64_encode(substr($mac, 0, 16)), '+/', '-_'), '=');
    }
}
