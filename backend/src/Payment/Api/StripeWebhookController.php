<?php

declare(strict_types=1);

namespace App\Payment\Api;

use App\Booking\Application\CheckoutService;
use App\Payment\Application\Dto\GatewayEvent;
use App\Payment\Application\InvalidWebhook;
use App\Payment\Application\PaymentGateway;
use App\Payment\Domain\PaymentRepository;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Stripe → us. Signature checked, each event processed at most once (event id recorded
 * first, forgotten again if processing fails so Stripe's retry is handled).
 */
#[AsController]
#[OA\Tag(name: 'Payment')]
final readonly class StripeWebhookController
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentRepository $payments,
        private CheckoutService $checkout,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/api/webhooks/stripe', name: 'api_webhook_stripe', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Processed, ignored, or already seen')]
    #[OA\Response(response: 400, description: 'Invalid signature or payload')]
    public function __invoke(Request $request): Response
    {
        try {
            $event = $this->gateway->parseWebhook($request->getContent(), (string) $request->headers->get('Stripe-Signature'));
        } catch (InvalidWebhook $e) {
            $this->logger->warning('Rejected Stripe webhook: {reason}', ['reason' => $e->getMessage()]);

            return new Response('invalid', Response::HTTP_BAD_REQUEST);
        }

        $handled = [GatewayEvent::INTENT_SUCCEEDED, GatewayEvent::INTENT_FAILED];
        if (!\in_array($event->type, $handled, true) || null === $event->intentId) {
            return new Response('ignored');
        }
        if (!$this->payments->recordWebhook($event->id, $event->type, $this->clock->now())) {
            return new Response('duplicate');
        }

        try {
            if (GatewayEvent::INTENT_SUCCEEDED === $event->type) {
                $this->checkout->paymentSucceeded($event->intentId);
            } else {
                $this->checkout->paymentFailed($event->intentId);
            }
        } catch (Throwable $e) {
            $this->payments->forgetWebhook($event->id);
            throw $e;
        }

        return new Response('ok');
    }
}
