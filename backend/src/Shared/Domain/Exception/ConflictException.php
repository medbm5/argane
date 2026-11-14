<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * The request is valid but conflicts with the current state (409).
 */
final class ConflictException extends DomainException
{
    /**
     * @param array<string, mixed> $context
     */
    public static function because(string $code, string $message, array $context = []): self
    {
        return new self($code, $message, 409, $context);
    }
}
