<?php

declare(strict_types=1);

namespace App\Identity\Api\Dto;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;

final readonly class MeResponse
{
    /**
     * @param list<string> $roles effective roles, hierarchy expanded
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public array $roles,
        public bool $isBoxOffice,
        public bool $isOrganizer,
    ) {
    }

    /**
     * @param array<string> $effectiveRoles
     */
    public static function from(User $user, array $effectiveRoles): self
    {
        $roles = array_values(array_unique($effectiveRoles));
        sort($roles);

        return new self(
            $user->id()->toRfc4122(),
            $user->email(),
            $user->displayName(),
            $roles,
            \in_array(Role::BOX_OFFICE->value, $roles, true),
            \in_array(Role::ORGANIZER->value, $roles, true),
        );
    }
}
