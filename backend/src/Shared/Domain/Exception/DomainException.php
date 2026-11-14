<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * Base class for business-rule violations. Each one carries a stable,
 * machine-readable error code exposed in the problem+json `code` field.
 */
class DomainException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context extra fields serialized into the problem document
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $httpStatus = 422,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
