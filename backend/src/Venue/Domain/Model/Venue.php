<?php

declare(strict_types=1);

namespace App\Venue\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'venue')]
#[ORM\UniqueConstraint(name: 'uniq_venue_slug', columns: ['slug'])]
class Venue
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 80)]
        private string $slug,
        #[ORM\Column(length: 80)]
        private string $city,
        #[ORM\Column(length: 200)]
        private string $address,
        #[ORM\Column(length: 64)]
        private string $timezone = 'Africa/Casablanca',
    ) {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function address(): string
    {
        return $this->address;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }
}
