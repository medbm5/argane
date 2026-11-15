<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Model\Role;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Staff roles granted at creation from `ORGANIZER_EMAILS` / `BOX_OFFICE_EMAILS`
 * (comma-separated, case-insensitive). Later changes go through app:user:grant.
 */
final readonly class RoleAllowlist
{
    /** @var list<string> */
    private array $organizers;

    /** @var list<string> */
    private array $boxOffice;

    public function __construct(
        #[Autowire('%env(default::ORGANIZER_EMAILS)%')]
        ?string $organizerEmails,
        #[Autowire('%env(default::BOX_OFFICE_EMAILS)%')]
        ?string $boxOfficeEmails,
    ) {
        $this->organizers = self::parse($organizerEmails);
        $this->boxOffice = self::parse($boxOfficeEmails);
    }

    /**
     * @return list<Role>
     */
    public function rolesFor(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $roles = [];
        if (\in_array($email, $this->organizers, true)) {
            $roles[] = Role::ORGANIZER;
        }
        if (\in_array($email, $this->boxOffice, true)) {
            $roles[] = Role::BOX_OFFICE;
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    private static function parse(?string $list): array
    {
        return array_values(array_filter(array_map(
            static fn (string $e): string => mb_strtolower(trim($e)),
            explode(',', (string) $list),
        )));
    }
}
