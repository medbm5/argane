<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * One demo account per role plus a pool of customers who own the generated orders.
 *
 * Demo accounts use Clerk test emails (`+clerk_test`, verification code 424242):
 * signing up in Clerk with one of them links the account to the seeded user.
 */
final class UserFixtures extends Fixture
{
    public const string ORGANIZER = 'user-organizer';
    public const string BOX_OFFICE = 'user-box-office';
    public const string CUSTOMER = 'user-customer';
    public const string CUSTOMER_PREFIX = 'customer-';
    public const int CUSTOMERS = 80;

    /** @var array<string, array{string, string, list<Role>}> */
    public const array DEMO_ACCOUNTS = [
        self::ORGANIZER => ['organisateur+clerk_test@example.com', 'Nadia Benali', [Role::ORGANIZER]],
        self::BOX_OFFICE => ['guichet+clerk_test@example.com', 'Youssef Amrani', [Role::BOX_OFFICE]],
        self::CUSTOMER => ['client+clerk_test@example.com', 'Salma Idrissi', []],
    ];

    public function load(ObjectManager $manager): void
    {
        $createdAt = new DateTimeImmutable('-120 days');

        foreach (self::DEMO_ACCOUNTS as $reference => [$email, $name, $roles]) {
            $user = new User(User::PLACEHOLDER_PREFIX.str_replace('user-', '', $reference), $email, $name, $createdAt);
            foreach ($roles as $role) {
                $user->grant($role);
            }
            $manager->persist($user);
            $this->addReference($reference, $user);
        }

        $faker = Factory::create('fr_FR');
        $faker->seed(2026);
        for ($i = 1; $i <= self::CUSTOMERS; ++$i) {
            $first = $faker->firstName();
            $last = $faker->lastName();
            $email = sprintf('%s.%s.%d@example.com', self::ascii($first), self::ascii($last), $i);
            $user = new User(sprintf('%scustomer_%03d', User::PLACEHOLDER_PREFIX, $i), $email, $first.' '.$last, $createdAt->modify(sprintf('+%d days', $faker->numberBetween(0, 60))));
            $manager->persist($user);
            $this->addReference(self::CUSTOMER_PREFIX.$i, $user);
        }

        $manager->flush();
    }

    private static function ascii(string $value): string
    {
        $ascii = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtolower((string) preg_replace('/[^a-z]/i', '', $ascii));
    }
}
