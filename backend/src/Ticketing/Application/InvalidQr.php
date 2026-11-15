<?php

declare(strict_types=1);

namespace App\Ticketing\Application;

use App\Shared\Domain\Exception\BusinessRuleException;

/** Unreadable, foreign or tampered QR code. */
final class InvalidQr
{
    public static function because(string $message): BusinessRuleException
    {
        return BusinessRuleException::because('QR_INVALID', $message, [], 422);
    }
}
