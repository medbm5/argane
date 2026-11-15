<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * A person known to the venue. Authentication is delegated to Clerk:
 * we only keep the Clerk subject, a copy of the email and our own roles.
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_clerk_id', columns: ['clerk_user_id'])]
#[ORM\Index(name: 'idx_user_email', columns: ['email'])]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 64)]
        private string $clerkUserId,
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column(length: 120)]
        private string $displayName,
        DateTimeImmutable $now,
    ) {
        $this->id = Uuid::v7();
        $this->email = mb_strtolower(trim($email));
        $this->createdAt = $now;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function clerkUserId(): string
    {
        return $this->clerkUserId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updateProfile(string $email, string $displayName): void
    {
        $this->email = mb_strtolower(trim($email));
        $this->displayName = $displayName;
    }

    /**
     * Highest explicit roles granted; every user is at least a customer.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([Role::CUSTOMER->value, ...$this->roles]));
    }

    public function hasRole(Role $role): bool
    {
        return \in_array($role->value, $this->getRoles(), true);
    }

    public function grant(Role $role): void
    {
        if (Role::CUSTOMER !== $role && !\in_array($role->value, $this->roles, true)) {
            $this->roles[] = $role->value;
        }
    }

    public function revoke(Role $role): void
    {
        $this->roles = array_values(array_filter($this->roles, static fn (string $r): bool => $r !== $role->value));
    }

    public function getUserIdentifier(): string
    {
        \assert('' !== $this->clerkUserId);

        return $this->clerkUserId;
    }

    public function eraseCredentials(): void
    {
    }
}
