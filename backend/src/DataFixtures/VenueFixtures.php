<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Venue\Application\LayoutGenerator;
use App\Venue\Application\StandardLayouts;
use App\Venue\Domain\Model\Venue;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Théâtre Argane (fictional venue, Agadir) with its théâtre and concert layouts.
 */
final class VenueFixtures extends Fixture
{
    public const string VENUE = 'venue';
    public const string LAYOUT_THEATRE = 'layout-theatre';
    public const string LAYOUT_CONCERT = 'layout-concert';

    public function __construct(private readonly LayoutGenerator $generator)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $venue = new Venue('Théâtre Argane', 'theatre-argane', 'Agadir', 'Boulevard du 20 Août, Agadir');
        $manager->persist($venue);

        $theatre = $this->generator->generate($venue, StandardLayouts::theatre());
        $concert = $this->generator->generate($venue, StandardLayouts::concert());
        $manager->persist($theatre);
        $manager->persist($concert);
        $manager->flush();

        $this->addReference(self::VENUE, $venue);
        $this->addReference(self::LAYOUT_THEATRE, $theatre);
        $this->addReference(self::LAYOUT_CONCERT, $concert);
    }
}
