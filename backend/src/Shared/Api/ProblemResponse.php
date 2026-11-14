<?php

declare(strict_types=1);

namespace App\Shared\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * RFC 7807 response (application/problem+json).
 */
final class ProblemResponse extends JsonResponse
{
    /**
     * @param array<string, mixed>               $problem
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(array $problem, int $status, array $headers = [])
    {
        parent::__construct($problem, $status, [...$headers, 'Content-Type' => 'application/problem+json']);
        $this->setEncodingOptions(\JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
    }
}
