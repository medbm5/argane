<?php

declare(strict_types=1);

namespace App\Shared\Domain;

enum SalesChannel: string
{
    case ONLINE = 'ONLINE';
    case BOX_OFFICE = 'BOX_OFFICE';

    public function label(): string
    {
        return match ($this) {
            self::ONLINE => 'En ligne',
            self::BOX_OFFICE => 'Guichet',
        };
    }
}
