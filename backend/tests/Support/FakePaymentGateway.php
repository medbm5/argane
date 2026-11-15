<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Payment\Application\Dto\GatewayEvent;
use App\Payment\Application\Dto\GatewayIntent;
use App\Payment\Application\InvalidWebhook;
use App\Payment\Application\PaymentGateway;
use RuntimeException;

/**
 * In-memory Stripe for tests. Webhooks are JSON `{"id","type","intent","amount"}` signed
 * with the header `Stripe-Signature: valid`. Intents can be moved to any status.
 */
final class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, GatewayIntent> */
    public array $intents = [];
    /** @var array<string, string> idempotency key → intent id */
    public array $keys = [];
    /** @var list<array{intent: string, amount: int, key: string}> */
    public array $refunds = [];
    /** @var list<string> */
    public array $cancelled = [];

    public function createIntent(int $amountCents, string $currency, string $idempotencyKey, array $metadata, ?string $receiptEmail = null): GatewayIntent
    {
        if (isset($this->keys[$idempotencyKey])) {
            return $this->intents[$this->keys[$idempotencyKey]];
        }
        $id = 'pi_fake_'.bin2hex(random_bytes(6));
        $this->keys[$idempotencyKey] = $id;

        return $this->intents[$id] = new GatewayIntent($id, 'requires_payment_method', $amountCents, $id.'_secret_x');
    }

    public function retrieveIntent(string $intentId): GatewayIntent
    {
        return $this->intents[$intentId] ?? throw new RuntimeException('No such intent '.$intentId);
    }

    public function cancelIntent(string $intentId): void
    {
        $this->cancelled[] = $intentId;
    }

    public function refund(string $intentId, int $amountCents, string $idempotencyKey): string
    {
        foreach ($this->refunds as $i => $refund) {
            if ($refund['key'] === $idempotencyKey) {
                return 're_fake_'.$i;
            }
        }
        $this->refunds[] = ['intent' => $intentId, 'amount' => $amountCents, 'key' => $idempotencyKey];

        return 're_fake_'.(\count($this->refunds) - 1);
    }

    public function parseWebhook(string $payload, string $signatureHeader): GatewayEvent
    {
        if ('valid' !== $signatureHeader) {
            throw new InvalidWebhook('bad signature');
        }
        /** @var array{id: string, type: string, intent?: string, amount?: int} $data */
        $data = json_decode($payload, true, flags: \JSON_THROW_ON_ERROR);

        return new GatewayEvent($data['id'], $data['type'], $data['intent'] ?? null, $data['amount'] ?? 0);
    }

    /** Simulates the card being charged (status seen by reconcile). */
    public function markSucceeded(string $intentId): void
    {
        $intent = $this->retrieveIntent($intentId);
        $this->intents[$intentId] = new GatewayIntent($intentId, GatewayIntent::SUCCEEDED, $intent->amountCents, $intent->clientSecret);
    }
}
