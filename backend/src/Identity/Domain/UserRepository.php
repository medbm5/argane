<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Model\User;
use Symfony\Component\Uid\Uuid;

interface UserRepository
{
    public function save(User $user): void;

    public function find(Uuid $id): ?User;

    public function findByClerkId(string $clerkUserId): ?User;

    public function findByEmail(string $email): ?User;
}
