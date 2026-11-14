<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * A request rejected by a business rule (422 by default).
 */
final class BusinessRuleException extends DomainException
{
    /**
     * @param array<string, mixed> $context
     */
    public static function because(string $code, string $message, array $context = [], int $status = 422): self
    {
        return new self($code, $message, $status, $context);
    }
}
