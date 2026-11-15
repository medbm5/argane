<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

enum EventCategory: string
{
    case CONCERT = 'CONCERT';
    case THEATRE = 'THEATRE';
    case CONFERENCE = 'CONFERENCE';
    case DANCE = 'DANCE';
    case COMEDY = 'COMEDY';

    public function label(): string
    {
        return match ($this) {
            self::CONCERT => 'Concert',
            self::THEATRE => 'Théâtre',
            self::CONFERENCE => 'Conférence',
            self::DANCE => 'Danse',
            self::COMEDY => 'Humour',
        };
    }
}
