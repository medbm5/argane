<?php

declare(strict_types=1);

namespace App\Booking\Domain;

use DateTimeImmutable;

interface OrderReferenceGenerator
{
    /** Public, human-friendly, unique reference such as "ARG-26-000123". */
    public function next(DateTimeImmutable $now): string;
}
