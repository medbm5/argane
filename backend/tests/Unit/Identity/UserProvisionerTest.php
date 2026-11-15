<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Application\ClerkDirectory;
use App\Identity\Application\Dto\ClerkProfile;
use App\Identity\Application\RoleAllowlist;
use App\Identity\Application\UserProvisioner;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Infrastructure\Clerk\ClerkBackendApiDirectory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class UserProvisionerTest extends TestCase
{
    private InMemoryUsers $users;
    private int $directoryCalls = 0;

    protected function setUp(): void
    {
        $this->users = new InMemoryUsers();
    }

    private function provisioner(string $organizers = '', string $boxOffice = ''): UserProvisioner
    {
        $directory = new class($this->directoryCalls) implements ClerkDirectory {
            public function __construct(private int &$calls)
            {
            }

            public function fetchProfile(string $clerkUserId): ClerkProfile
            {
                ++$this->calls;

                return new ClerkProfile($clerkUserId, 'Owner@Argane.test', 'Nadia Benali');
            }
        };

        return new UserProvisioner(
            $this->users,
            $directory,
            new RoleAllowlist($organizers, $boxOffice),
            new MockClock('2026-11-15 12:00:00'),
            new NullLogger(),
        );
    }

    public function testCreatesAnUnknownUserFromClerk(): void
    {
        $user = $this->provisioner()->forClerkUser('user_new');

        self::assertSame('user_new', $user->clerkUserId());
        self::assertSame('owner@argane.test', $user->email());
        self::assertSame('Nadia Benali', $user->displayName());
        self::assertSame(['ROLE_CUSTOMER'], $user->getRoles());
        self::assertSame($user, $this->users->findByClerkId('user_new'));
    }

    public function testReturnsAKnownUserWithoutCallingClerk(): void
    {
        $provisioner = $this->provisioner();
        $first = $provisioner->forClerkUser('user_known');
        $second = $provisioner->forClerkUser('user_known');

        self::assertSame($first, $second);
        self::assertSame(1, $this->directoryCalls);
    }

    public function testGrantsAllowlistedRolesCaseInsensitively(): void
    {
        $user = $this->provisioner(organizers: ' owner@argane.test , other@x.test')->forClerkUser('user_owner');

        self::assertTrue(\in_array('ROLE_ORGANIZER', $user->getRoles(), true));
        self::assertFalse(\in_array('ROLE_BOX_OFFICE', $user->getRoles(), true));
    }

    public function testGrantsBoxOfficeRole(): void
    {
        $user = $this->provisioner(boxOffice: 'OWNER@argane.test')->forClerkUser('user_desk');

        self::assertSame(['ROLE_CUSTOMER', 'ROLE_BOX_OFFICE'], $user->getRoles());
    }

    public function testMapsTheClerkUserObject(): void
    {
        $profile = ClerkBackendApiDirectory::mapUser('user_1', [
            'primary_email_address_id' => 'idn_2',
            'email_addresses' => [
                ['id' => 'idn_1', 'email_address' => 'old@example.test'],
                ['id' => 'idn_2', 'email_address' => 'primary@example.test'],
            ],
            'first_name' => 'Youssef',
            'last_name' => null,
        ]);

        self::assertSame('primary@example.test', $profile->email);
        self::assertSame('Youssef', $profile->displayName);
    }

    public function testFallsBackToTheEmailLocalPartForTheName(): void
    {
        $profile = ClerkBackendApiDirectory::mapUser('user_1', [
            'email_addresses' => [['id' => 'idn_1', 'email_address' => 'salma@example.test']],
        ]);

        self::assertSame('salma', $profile->displayName);
    }
}

final class InMemoryUsers implements UserRepository
{
    /** @var array<string, User> */
    private array $byClerkId = [];

    public function save(User $user): void
    {
        $this->byClerkId[$user->clerkUserId()] = $user;
    }

    public function find(Uuid $id): ?User
    {
        foreach ($this->byClerkId as $user) {
            if ($user->id()->equals($id)) {
                return $user;
            }
        }

        return null;
    }

    public function findByClerkId(string $clerkUserId): ?User
    {
        return $this->byClerkId[$clerkUserId] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->byClerkId as $user) {
            if ($user->email() === mb_strtolower($email)) {
                return $user;
            }
        }

        return null;
    }
}
