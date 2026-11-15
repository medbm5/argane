<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class UserTest extends TestCase
{
    private function user(): User
    {
        return new User('user_123', '  Fatima@Example.COM ', 'Fatima', new DateTimeImmutable('2026-11-15T10:00:00Z'));
    }

    public function testEveryUserIsACustomer(): void
    {
        $user = $this->user();

        self::assertSame(['ROLE_CUSTOMER'], $user->getRoles());
        self::assertTrue($user->hasRole(Role::CUSTOMER));
        self::assertSame('user_123', $user->getUserIdentifier());
    }

    public function testNormalizesEmail(): void
    {
        self::assertSame('fatima@example.com', $this->user()->email());
    }

    public function testGrantAndRevokeAreIdempotent(): void
    {
        $user = $this->user();
        $user->grant(Role::BOX_OFFICE);
        $user->grant(Role::BOX_OFFICE);

        self::assertSame(['ROLE_CUSTOMER', 'ROLE_BOX_OFFICE'], $user->getRoles());

        $user->revoke(Role::BOX_OFFICE);
        self::assertSame(['ROLE_CUSTOMER'], $user->getRoles());
    }

    public function testIdIsAUuidV7(): void
    {
        self::assertInstanceOf(UuidV7::class, $this->user()->id());
    }
}
