<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure;

use App\Payment\Application\Dto\GatewayEvent;
use App\Payment\Application\Dto\GatewayIntent;
use App\Payment\Application\InvalidWebhook;
use App\Payment\Application\PaymentGateway;
use App\Shared\Domain\Exception\BusinessRuleException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use UnexpectedValueException;

final class StripePaymentGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $secretKey,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private readonly string $webhookSecret,
    ) {
    }

    public function createIntent(int $amountCents, string $currency, string $idempotencyKey, array $metadata, ?string $receiptEmail = null): GatewayIntent
    {
        $params = [
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => $metadata,
            'description' => 'Théâtre Argane '.($metadata['reference'] ?? ''),
        ];
        if (null !== $receiptEmail) {
            $params['receipt_email'] = $receiptEmail;
        }

        return self::intent($this->client()->paymentIntents->create($params, ['idempotency_key' => $idempotencyKey]));
    }

    public function retrieveIntent(string $intentId): GatewayIntent
    {
        return self::intent($this->client()->paymentIntents->retrieve($intentId));
    }

    public function cancelIntent(string $intentId): void
    {
        $intent = $this->client()->paymentIntents->retrieve($intentId);
        if (\in_array($intent->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
            $this->client()->paymentIntents->cancel($intentId);
        }
    }

    public function refund(string $intentId, int $amountCents, string $idempotencyKey): string
    {
        return $this->client()->refunds->create(
            ['payment_intent' => $intentId, 'amount' => $amountCents],
            ['idempotency_key' => $idempotencyKey],
        )->id;
    }

    public function parseWebhook(string $payload, string $signatureHeader): GatewayEvent
    {
        if ('' === $this->webhookSecret) {
            throw new InvalidWebhook('STRIPE_WEBHOOK_SECRET is not configured.');
        }
        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new InvalidWebhook($e->getMessage(), previous: $e);
        }

        $object = $event->data->object ?? null;
        if (!$object instanceof PaymentIntent) {
            return new GatewayEvent($event->id, $event->type, null);
        }

        return new GatewayEvent(
            $event->id,
            $event->type,
            $object->id,
            (int) $object->amount,
            self::errorMessage($object->last_payment_error),
        );
    }

    private function client(): StripeClient
    {
        if ('' === $this->secretKey) {
            throw BusinessRuleException::because('PAYMENTS_NOT_CONFIGURED', 'Le paiement en ligne n’est pas configuré sur ce serveur.', [], 503);
        }

        return $this->client ??= new StripeClient(['api_key' => $this->secretKey, 'max_network_retries' => 2]);
    }

    private static function intent(PaymentIntent $intent): GatewayIntent
    {
        return new GatewayIntent(
            $intent->id,
            $intent->status,
            (int) $intent->amount,
            $intent->client_secret,
            self::errorMessage($intent->last_payment_error),
        );
    }

    private static function errorMessage(?StripeObject $error): ?string
    {
        return null !== $error && isset($error['message']) ? (string) $error['message'] : null;
    }
}
