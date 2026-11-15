<?php

declare(strict_types=1);

namespace App\Payment\Application;

use App\Payment\Application\Dto\GatewayEvent;
use App\Payment\Application\Dto\GatewayIntent;

/**
 * Port to the card processor (Stripe in production, a fake in tests). Amounts are minor
 * units; currency is the ISO code. Idempotency keys make every write safe to retry.
 */
interface PaymentGateway
{
    /**
     * @param array<string, string> $metadata copied onto the intent (order id, reference)
     */
    public function createIntent(int $amountCents, string $currency, string $idempotencyKey, array $metadata, ?string $receiptEmail = null): GatewayIntent;

    public function retrieveIntent(string $intentId): GatewayIntent;

    /** Best effort: an intent already succeeded or cancelled is left alone. */
    public function cancelIntent(string $intentId): void;

    /** Refunds (part of) a succeeded intent; returns the processor's refund id. */
    public function refund(string $intentId, int $amountCents, string $idempotencyKey): string;

    /**
     * Verifies the signature and decodes a webhook.
     *
     * @throws InvalidWebhook
     */
    public function parseWebhook(string $payload, string $signatureHeader): GatewayEvent;
}
