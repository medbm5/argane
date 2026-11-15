<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\Exception\BusinessRuleException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/** Consumes one token for `key` or answers 429 with a problem body and Retry-After. */
final class RateLimit
{
    public static function consume(RateLimiterFactoryInterface $factory, string $key, string $code, string $message): void
    {
        $limit = $factory->create($key)->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            throw BusinessRuleException::because($code, $message, ['retryAfter' => $retryAfter], 429);
        }
    }
}
