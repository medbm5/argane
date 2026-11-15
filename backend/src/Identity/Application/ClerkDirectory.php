<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Dto\ClerkProfile;

/**
 * Read access to Clerk's user directory (Backend API).
 */
interface ClerkDirectory
{
    public function fetchProfile(string $clerkUserId): ClerkProfile;
}
