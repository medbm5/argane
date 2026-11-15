<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Application\PerformancePublisher;
use App\Catalog\Domain\InventoryRepository;
use App\Catalog\Domain\Model\Event;
use App\Catalog\Domain\Model\EventCategory;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\PerformanceRepository;
use App\Pricing\Domain\Model\PerformancePrice;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\SalesChannel;
use App\Shared\Domain\VenueTime;
use App\Venue\Domain\Model\HallLayout;
use App\Venue\Domain\Model\Venue;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

/**
 * Tariffs and the season 26/27 programme. Dates are relative to the day the
 * fixtures are loaded so the demo always has upcoming and past performances.
 */
final class CatalogFixtures extends Fixture implements DependentFixtureInterface
{
    public const string TARIFF_PREFIX = 'tariff-';
    public const string PERFORMANCE_PREFIX = 'performance-';
    public const string EVENT_PREFIX = 'event-';

    /** Days before the curtain when sales open. */
    public const int SALES_LEAD_DAYS = 80;

    /**
     * key => [title, subtitle, category, duration, age, layout, description, [day offsets], [overrides]].
     *
     * @var array<string, array{string, ?string, EventCategory, int, ?int, string, string, list<int>}>
     */
    private const array PROGRAMME = [
        'tamra' => ['Tamra — nuits gnaoua', 'Maâlem Hamid Ouchen et invités', EventCategory::CONCERT, 120, null, 'concert',
            'Trois nuits de transe gnaoua, des qraqeb aux guembris. Fosse debout pour danser, corbeille pour écouter.', [12, 13, 40]],
        'misanthrope' => ['Le Misanthrope', 'Molière — Compagnie du Souss', EventCategory::THEATRE, 140, 10, 'theatre',
            'Alceste ne supporte plus les politesses de salon. Une mise en scène sobre, des alexandrins tenus comme une lame.', [5, 6, 19, 20]],
        'cyrano' => ['Cyrano de Bergerac', 'Edmond Rostand', EventCategory::THEATRE, 165, 8, 'theatre',
            'Le panache, la lune, le balcon. Douze comédiens, un nez et quelques duels pour la pièce la plus aimée du répertoire.', [-10, 26, 27]],
        'quatuor' => ['Quatuor Ambre — Les Saisons', 'Vivaldi, Piazzolla', EventCategory::CONCERT, 90, null, 'theatre',
            'Les quatre saisons de Vivaldi répondent à celles de Buenos Aires. Quatre archets, un même souffle.', [33, 47]],
        'atlantique' => ['L’Atlantique, mémoire des marins', 'Conférence illustrée', EventCategory::CONFERENCE, 75, null, 'theatre',
            'Récits de pêcheurs d’Agadir et d’Essaouira, cartes anciennes et photographies d’archives. Entrée sur invitation ou à petit prix.', [9]],
        'ahwach' => ['Ahwach, corps et cercle', 'Collectif Tiwizi', EventCategory::DANCE, 70, null, 'theatre',
            'La danse collective de l’Atlas revisitée par huit danseurs contemporains. Un cercle, des tambours, une respiration.', [16, 54]],
        'rire' => ['Rire à l’ouest', 'Plateau humour', EventCategory::COMEDY, 95, 12, 'theatre',
            'Quatre humoristes du Souss et d’ailleurs, en darija et en français, sans filet.', [23, 24]],
        'jazz' => ['Jazz sur la baie', 'Trio Lune Noire', EventCategory::CONCERT, 100, null, 'concert',
            'Contrebasse, piano, batterie et la baie d’Agadir en toile de fond sonore.', [-25]],
    ];

    /**
     * Prices in MAD per layout zone: [FULL, REDUCED, GROUP] (INVITATION is always 0, LOGES has no GROUP).
     *
     * @var array<string, array{int, int, ?int}>
     */
    private const array PRICES = [
        'ORCHESTRE' => [250, 150, 180],
        'CORBEILLE' => [180, 110, 130],
        'LOGES' => [350, 250, null],
        'FOSSE' => [200, 150, 150],
    ];

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly PerformanceRepository $performances,
        private readonly InventoryRepository $inventory,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getDependencies(): array
    {
        return [VenueFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $venue = $this->getReference(VenueFixtures::VENUE, Venue::class);
        $layouts = [
            'theatre' => $this->getReference(VenueFixtures::LAYOUT_THEATRE, HallLayout::class),
            'concert' => $this->getReference(VenueFixtures::LAYOUT_CONCERT, HallLayout::class),
        ];
        $tariffs = $this->loadTariffs($manager, $venue);
        $today = $this->clock->now()->setTime(0, 0);

        foreach (self::PROGRAMME as $key => [$title, $subtitle, $category, $duration, $age, $layoutKey, $description, $days]) {
            $event = new Event($venue, $title, $category, $duration, null, $today->modify('-100 days'));
            $event->describe($title, $subtitle, $category, $description, $duration, $age, '/posters/'.$event->slug().'.svg');
            $event->publish($today->modify('-90 days'));
            $manager->persist($event);
            $this->addReference(self::EVENT_PREFIX.$key, $event);

            foreach ($days as $index => $day) {
                $local = $today->modify(sprintf('%+d days', $day))->setTimezone(new DateTimeZone(VenueTime::DEFAULT_TIMEZONE))->format('Y-m-d');
                $startsAt = VenueTime::fromVenueLocal($local.' '.('quatuor' === $key ? '19:00' : '20:00'));
                $salesOpenAt = $startsAt->modify(sprintf('-%d days', self::SALES_LEAD_DAYS))->setTime(9, 0);
                $performance = Performance::schedule($event, $layouts[$layoutKey], $startsAt, $salesOpenAt);
                $manager->persist($performance);
                $manager->flush();

                // Publish "as of" the sales opening so past performances get inventory too.
                $publisher = new PerformancePublisher($this->performances, $this->inventory, $this->em, new MockClock($salesOpenAt));
                $publisher->publish($performance, openSales: true);

                $this->priceGrid($manager, $performance, $tariffs, 'atlantique' === $key);
                $this->addReference(self::PERFORMANCE_PREFIX.$key.'-'.($index + 1), $performance);
            }
        }

        $manager->flush();
    }

    /**
     * @return array<string, Tariff>
     */
    private function loadTariffs(ObjectManager $manager, Venue $venue): array
    {
        $both = [SalesChannel::ONLINE, SalesChannel::BOX_OFFICE];
        $tariffs = [
            Tariff::FULL => new Tariff($venue, Tariff::FULL, 'Plein', $both),
            Tariff::REDUCED => new Tariff($venue, Tariff::REDUCED, 'Réduit', $both, requiresProofAtEntry: true, description: 'Étudiants, moins de 25 ans, demandeurs d’emploi : justificatif à l’entrée.'),
            Tariff::GROUP => new Tariff($venue, Tariff::GROUP, 'Groupe', $both, minQuantity: 10, maxQuantity: 30, description: 'À partir de 10 places, jusqu’à 30.'),
            Tariff::INVITATION => new Tariff($venue, Tariff::INVITATION, 'Invitation', [SalesChannel::BOX_OFFICE], ['ROLE_BOX_OFFICE'], isFree: true, description: 'Presse, partenaires, artistes. Motif obligatoire.'),
        ];
        $order = 0;
        foreach ($tariffs as $code => $tariff) {
            $tariff->update($tariff->label(), $tariff->channels(), $tariff->allowedRoles(), $tariff->minQuantity(), $tariff->maxQuantity(), $tariff->requiresProofAtEntry(), $tariff->isFree(), $tariff->description(), $order++);
            $manager->persist($tariff);
            $this->addReference(self::TARIFF_PREFIX.$code, $tariff);
        }

        return $tariffs;
    }

    /**
     * @param array<string, Tariff> $tariffs
     */
    private function priceGrid(ObjectManager $manager, Performance $performance, array $tariffs, bool $conference): void
    {
        foreach ($performance->layout()->zones() as $zone) {
            [$full, $reduced, $group] = self::PRICES[$zone->code()];
            if ($conference) {
                // A talk: small prices, same grid shape.
                [$full, $reduced, $group] = [80, 50, null === $group ? null : 60];
            }
            $manager->persist(new PerformancePrice($performance, $zone, $tariffs[Tariff::FULL], $full * 100));
            $manager->persist(new PerformancePrice($performance, $zone, $tariffs[Tariff::REDUCED], $reduced * 100));
            if (null !== $group) {
                $manager->persist(new PerformancePrice($performance, $zone, $tariffs[Tariff::GROUP], $group * 100));
            }
            $manager->persist(new PerformancePrice($performance, $zone, $tariffs[Tariff::INVITATION], 0));
        }
    }

    /**
     * Upcoming performance keys created by this fixture, in programme order.
     *
     * @return list<string>
     */
    public static function performanceKeys(): array
    {
        $keys = [];
        foreach (self::PROGRAMME as $key => $definition) {
            foreach (array_keys($definition[7]) as $index) {
                $keys[] = $key.'-'.($index + 1);
            }
        }

        return $keys;
    }

    /** Helper for other fixtures: local wall-clock date of a UTC instant. */
    public static function localDay(DateTimeImmutable $instant): string
    {
        return VenueTime::toVenue($instant)->format('Y-m-d');
    }
}
