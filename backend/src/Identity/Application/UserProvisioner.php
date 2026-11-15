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
        private RoleAllowlist $allowlist,
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

        $seeded = $this->users->findByEmail($profile->email);
        if (null !== $seeded && $seeded->isPlaceholder()) {
            $seeded->linkClerkAccount($clerkUserId);
            $this->users->save($seeded);
            $this->logger->info('Linked Clerk account to seeded user', ['clerkUserId' => $clerkUserId]);

            return $seeded;
        }

        $user = new User($profile->clerkUserId, $profile->email, $profile->displayName, $this->clock->now());
        foreach ($this->allowlist->rolesFor($profile->email) as $role) {
            $user->grant($role);
        }
        $this->users->save($user);
        $this->logger->info('Provisioned user from Clerk', ['clerkUserId' => $clerkUserId, 'roles' => $user->getRoles()]);

        return $user;
    }
}
