<?php

declare(strict_types=1);

namespace App\Venue\Application\Dto;

enum SeatArrangement: string
{
    /** Rows stacked front to back, seats numbered left to right. */
    case GRID = 'GRID';
    /** Each "row" is a box; boxes split between both side walls. */
    case BOXES = 'BOXES';
}
