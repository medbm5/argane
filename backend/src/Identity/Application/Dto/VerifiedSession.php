<?php

declare(strict_types=1);

namespace App\Identity\Application\Dto;

use DateTimeImmutable;

/**
 * Claims we rely on from a verified Clerk session token.
 */
final readonly class VerifiedSession
{
    public function __construct(
        public string $clerkUserId,
        public ?string $sessionId,
        public ?string $authorizedParty,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
