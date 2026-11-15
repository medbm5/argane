<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\UserRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Just-in-time provisioning: the first authenticated request with an unknown
 * Clerk subject creates the local user from Clerk's Backend API.
 */
final readonly class UserProvisioner
{
    public function __construct(
        private UserRepository $users,
        private ClerkDirectory $directory,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function forClerkUser(string $clerkUserId): User
    {
        $existing = $this->users->findByClerkId($clerkUserId);
        if (null !== $existing) {
            return $existing;
        }

        $profile = $this->directory->fetchProfile($clerkUserId);
        $user = new User($profile->clerkUserId, $profile->email, $profile->displayName, $this->clock->now());
        $this->users->save($user);
        $this->logger->info('Provisioned user from Clerk', ['clerkUserId' => $clerkUserId]);

        return $user;
    }
}
