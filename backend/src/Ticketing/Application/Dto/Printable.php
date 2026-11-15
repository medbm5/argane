<?php

declare(strict_types=1);

namespace App\Ticketing\Application\Dto;

/** A rendered PDF and the file name it should download as. */
final readonly class Printable
{
    public function __construct(
        public string $filename,
        public string $bytes,
    ) {
    }
}
