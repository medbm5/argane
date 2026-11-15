<?php

declare(strict_types=1);

namespace App\Payment\Domain\Model;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Processed Stripe events, keyed by Stripe's event id: a replayed webhook is a no-op.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stripe_webhook_event')]
class StripeWebhookEvent
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 255)]
        private string $id,
        #[ORM\Column(length: 80)]
        private string $type,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $processedAt,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function processedAt(): DateTimeImmutable
    {
        return $this->processedAt;
    }
}
