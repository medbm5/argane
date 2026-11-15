<?php

declare(strict_types=1);

namespace App\Booking\Application\Message;

/** Scheduled every minute: housekeeping for holds past their expiry. */
final readonly class ExpireHolds
{
}
