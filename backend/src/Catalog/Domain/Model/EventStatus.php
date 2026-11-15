<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

enum EventStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case ARCHIVED = 'ARCHIVED';
}
