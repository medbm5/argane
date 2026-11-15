<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Dto\VerifiedSession;

interface SessionTokenVerifier
{
    /**
     * @throws InvalidSessionToken when the token is malformed, unsigned by Clerk, expired or not meant for us
     */
    public function verify(string $token): VerifiedSession;
}
