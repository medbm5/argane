<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }

    public function find(Uuid $id): ?User
    {
        return $this->em->find(User::class, $id);
    }

    public function findByClerkId(string $clerkUserId): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['clerkUserId' => $clerkUserId]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
    }
}
