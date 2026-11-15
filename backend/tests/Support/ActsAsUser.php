<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Identity\Application\ClerkDirectory;
use App\Identity\Application\Dto\ClerkProfile;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Clerk\JwksProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

/**
 * For WebTestCase: stub Clerk (JWKS + directory) and create users with roles, then
 * sign bearer tokens relative to the mocked clock.
 *
 * @mixin WebTestCase
 */
trait ActsAsUser
{
    /** Keeps the kernel (and these stubs) alive across requests made by the client. */
    protected static function stubClerk(KernelBrowser $client): void
    {
        $client->disableReboot();
        $container = static::getContainer();
        $container->set(JwksProvider::class, new class extends JwksProvider {
            public function __construct()
            {
            }

            public function keySet(bool $refresh = false): array
            {
                return TestTokens::jwks();
            }
        });
        $container->set(ClerkDirectory::class, new class implements ClerkDirectory {
            public function fetchProfile(string $clerkUserId): ClerkProfile
            {
                return new ClerkProfile($clerkUserId, $clerkUserId.'@argane.test', 'Test '.$clerkUserId);
            }
        });
    }

    protected static function createUser(string $clerkUserId, Role ...$roles): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User($clerkUserId, $clerkUserId.'@argane.test', 'Test '.$clerkUserId, Clock::get()->now());
        foreach ($roles as $role) {
            $user->grant($role);
        }
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * Server parameters carrying a valid bearer token for this user.
     *
     * @return array<string, string>
     */
    protected static function bearer(User $user): array
    {
        $now = Clock::get()->now()->getTimestamp();

        return ['HTTP_AUTHORIZATION' => 'Bearer '.TestTokens::sign($user->clerkUserId(), ['iat' => $now - 10, 'nbf' => $now - 10, 'exp' => $now + 600])];
    }
}
