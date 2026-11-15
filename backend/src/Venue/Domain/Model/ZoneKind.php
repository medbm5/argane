<?php

declare(strict_types=1);

namespace App\Venue\Domain\Model;

enum ZoneKind: string
{
    case SEATED = 'SEATED';
    case STANDING = 'STANDING';
}
