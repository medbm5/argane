<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class NotFoundException extends DomainException
{
    public static function of(string $resource, string $id): self
    {
        return new self('NOT_FOUND', sprintf('%s introuvable (%s).', $resource, $id), 404, ['resource' => $resource]);
    }
}
