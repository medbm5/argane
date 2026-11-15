<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

/**
 * Application roles. Stored in our database, never in Clerk.
 * Hierarchy: ORGANIZER > BOX_OFFICE > CUSTOMER.
 */
enum Role: string
{
    case CUSTOMER = 'ROLE_CUSTOMER';
    case BOX_OFFICE = 'ROLE_BOX_OFFICE';
    case ORGANIZER = 'ROLE_ORGANIZER';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Client',
            self::BOX_OFFICE => 'Guichetier',
            self::ORGANIZER => 'Organisateur',
        };
    }
}
