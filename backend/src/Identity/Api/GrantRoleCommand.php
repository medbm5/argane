<?php

declare(strict_types=1);

namespace App\Identity\Api;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:grant', description: 'Grant (or revoke) a staff role to an existing user, by email.')]
final readonly class GrantRoleCommand
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email of a user who has signed in at least once')]
        string $email,
        #[Argument(description: 'ROLE_BOX_OFFICE or ROLE_ORGANIZER')]
        string $role,
        #[Option(description: 'Remove the role instead of granting it')]
        bool $revoke = false,
    ): int {
        $parsed = Role::tryFrom(strtoupper($role));
        if (null === $parsed || Role::CUSTOMER === $parsed) {
            $io->error(sprintf('Unknown staff role "%s". Use ROLE_BOX_OFFICE or ROLE_ORGANIZER.', $role));

            return Command::INVALID;
        }

        $user = $this->users->findByEmail($email);
        if (null === $user) {
            $io->error(sprintf('No user with email %s. They must sign in once before a role can be granted.', $email));

            return Command::FAILURE;
        }

        $revoke ? $user->revoke($parsed) : $user->grant($parsed);
        $this->users->save($user);

        $io->success(sprintf('%s now has: %s', $user->email(), implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
