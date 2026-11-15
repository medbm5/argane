<?php

declare(strict_types=1);

namespace App\Identity\Application\Dto;

final readonly class ClerkProfile
{
    public function __construct(
        public string $clerkUserId,
        public string $email,
        public string $displayName,
    ) {
    }
}
