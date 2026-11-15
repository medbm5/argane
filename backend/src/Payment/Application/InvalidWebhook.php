<?php

declare(strict_types=1);

namespace App\Payment\Application;

use RuntimeException;

/** Bad signature or unreadable payload: answered with 400 so the sender stops retrying it. */
final class InvalidWebhook extends RuntimeException
{
}
