<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Booking\Domain\Model\Order;
use App\Booking\Domain\OrderReferenceGenerator;
use App\Catalog\Domain\Model\Performance;
use App\DataFixtures\Demo\SalesWriter;
use App\Identity\Domain\Model\User;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\SalesChannel;
use App\Venue\Domain\Model\Zone;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use LogicException;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * ~90 days of deterministic sales (Faker seed 2026):
 *  - booking curve: slow start, spike at announcement, rise in the last 10 days;
 *  - channel mix ≈ 65 % online / 35 % box office;
 *  - tariffs mostly Plein/Réduit, some groups, invitations at the counter;
 *  - past performances get door check-ins.
 */
final class SalesFixtures extends Fixture implements DependentFixtureInterface
{
    /** Target fill (share of sellable places) once a performance has fully sold its curve. */
    public const array TARGET_FILL = [
        'tamra-1' => 0.90, 'tamra-2' => 0.82, 'tamra-3' => 0.70,
        'misanthrope-1' => 0.88, 'misanthrope-2' => 0.80, 'misanthrope-3' => 0.62, 'misanthrope-4' => 0.66,
        'cyrano-1' => 0.93, 'cyrano-2' => 0.74, 'cyrano-3' => 0.78,
        'quatuor-1' => 0.58, 'quatuor-2' => 0.45,
        'atlantique-1' => 0.55,
        'ahwach-1' => 0.50, 'ahwach-2' => 0.30,
        'rire-1' => 0.72, 'rire-2' => 0.76,
        'jazz-1' => 0.86,
    ];

    /** Zone popularity among buyers. */
    private const array ZONE_WEIGHTS = ['ORCHESTRE' => 55, 'FOSSE' => 60, 'CORBEILLE' => 30, 'LOGES' => 12];

    private Generator $faker;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderReferenceGenerator $references,
        private readonly ClockInterface $clock,
        #[Autowire('%app.currency%')]
        private readonly string $currency,
    ) {
    }

    public function getDependencies(): array
    {
        return [CatalogFixtures::class, UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $this->faker = Factory::create('fr_FR');
        $this->faker->seed(2026);
        mt_srand(2026);
        $this->em->getConnection()->executeStatement('DELETE FROM order_sequence');

        $writer = new SalesWriter($this->em, $this->em->getConnection(), $this->references, $this->currency);
        $tariffs = [];
        foreach ([Tariff::FULL, Tariff::REDUCED, Tariff::GROUP, Tariff::INVITATION] as $code) {
            $tariffs[$code] = $this->getReference(CatalogFixtures::TARIFF_PREFIX.$code, Tariff::class);
        }
        $customers = [];
        for ($i = 1; $i <= UserFixtures::CUSTOMERS; ++$i) {
            $customers[] = $this->getReference(UserFixtures::CUSTOMER_PREFIX.$i, User::class);
        }
        $seller = $this->getReference(UserFixtures::BOX_OFFICE, User::class);
        $demoCustomer = $this->getReference(UserFixtures::CUSTOMER, User::class);
        $now = $this->clock->now();

        foreach (CatalogFixtures::performanceKeys() as $key) {
            $performance = $this->getReference(CatalogFixtures::PERFORMANCE_PREFIX.$key, Performance::class);
            $writer->load($performance);
            $this->demoCustomerOrders($writer, $key, $performance, $tariffs, $demoCustomer);

            $capacity = $writer->totalRemaining($performance);
            $target = (int) round($capacity * (self::TARGET_FILL[$key] ?? 0.5));
            $invitationShare = str_starts_with($key, 'atlantique') ? 0.4 : 0.06;

            foreach ($this->orderTimes($performance, $target, $now) as $at) {
                if ($writer->totalRemaining($performance) <= 2) {
                    break;
                }
                $this->randomOrder($writer, $performance, $at, $tariffs, $customers, $seller, $invitationShare);
            }

            $writer->flushStanding($performance);
            if ($performance->isPast($now)) {
                $this->checkIns($performance, $seller);
            }
        }

        $manager->flush();
    }

    /**
     * Order timestamps following the booking curve, keeping only those already in the past.
     *
     * @return list<DateTimeImmutable>
     */
    private function orderTimes(Performance $performance, int $targetTickets, DateTimeImmutable $now): array
    {
        $open = $performance->salesOpenAt();
        $close = min($performance->startsAt(), $performance->salesCloseAt());
        $spanDays = max(1, (int) floor(($close->getTimestamp() - $open->getTimestamp()) / 86400));

        // Relative daily weights: announcement spike, quiet middle, final rush.
        $weights = [];
        for ($d = 0; $d < $spanDays; ++$d) {
            $daysLeft = $spanDays - $d;
            $weights[$d] = match (true) {
                $d < 3 => 5.0 - $d,
                $daysLeft <= 10 => 1.5 + (10 - $daysLeft) * 0.45,
                default => 0.6,
            };
        }
        $total = array_sum($weights);
        $orders = (int) round($targetTickets / 2.6);

        $times = [];
        foreach ($weights as $d => $weight) {
            $count = $orders * $weight / $total;
            $n = (int) floor($count) + (mt_rand(0, 1000) / 1000 < $count - floor($count) ? 1 : 0);
            for ($k = 0; $k < $n; ++$k) {
                $at = $open->modify(sprintf('+%d days +%d minutes', $d, mt_rand(60, 13 * 60)));
                if ($at < $now && $at < $close) {
                    $times[] = $at;
                }
            }
        }
        sort($times);

        return $times;
    }

    /**
     * @param array<string, Tariff> $tariffs
     * @param list<User>            $customers
     */
    private function randomOrder(SalesWriter $writer, Performance $performance, DateTimeImmutable $at, array $tariffs, array $customers, User $seller, float $invitationShare): void
    {
        $online = mt_rand(1, 100) <= 65;
        $group = mt_rand(1, 1000) <= 18;
        $quantity = $group ? mt_rand(10, 18) : $this->weighted([1 => 18, 2 => 46, 3 => 14, 4 => 16, 5 => 4, 6 => 2]);
        $invitation = !$online && !$group && mt_rand(1, 1000) <= (int) ($invitationShare * 1000 / 0.35);

        $zone = $this->pickZone($writer, $performance, $quantity, $group ? Tariff::GROUP : null);
        if (null === $zone) {
            return;
        }

        $seats = $zone->isStanding()
            ? array_fill(0, $quantity, null)
            : $writer->pickSeats($performance, $zone, $quantity, $online ? 2.5 : 4.0);
        if (null === $seats) {
            return;
        }

        $lines = [];
        foreach ($seats as $seat) {
            $tariffCode = match (true) {
                $invitation => Tariff::INVITATION,
                $group => Tariff::GROUP,
                default => mt_rand(1, 100) <= 68 ? Tariff::FULL : Tariff::REDUCED,
            };
            $lines[] = ['zone' => $zone, 'seat' => $seat, 'tariff' => $tariffs[$tariffCode]];
        }

        if ($online) {
            $customer = $customers[array_rand($customers)];
            $writer->sell($performance, $lines, SalesChannel::ONLINE, $at, $customer->id(), null);
        } else {
            $writer->sell(
                $performance,
                $lines,
                SalesChannel::BOX_OFFICE,
                $at,
                null,
                $seller->id(),
                $this->faker->name(),
                $invitation ? $this->faker->randomElement(['Presse locale', 'Partenaire Souss Radio', 'Artistes invités', 'Mécène Atlas Imprimerie']) : null,
            );
        }
    }

    private function pickZone(SalesWriter $writer, Performance $performance, int $quantity, ?string $tariffCode): ?Zone
    {
        $weights = [];
        $zones = [];
        foreach ($performance->layout()->zones() as $zone) {
            if ($writer->remaining($performance, $zone) < $quantity) {
                continue;
            }
            if (null !== $tariffCode && !$writer->offers($performance, $zone, $tariffCode)) {
                continue;
            }
            $zones[$zone->code()] = $zone;
            $weights[$zone->code()] = self::ZONE_WEIGHTS[$zone->code()] ?? 10;
        }
        if ([] === $zones) {
            return null;
        }

        return $zones[$this->weighted($weights)];
    }

    /**
     * The demo customer account owns a few orders so "Mes billets" is not empty.
     *
     * @param array<string, Tariff> $tariffs
     */
    private function demoCustomerOrders(SalesWriter $writer, string $key, Performance $performance, array $tariffs, User $customer): void
    {
        $plan = [
            'misanthrope-1' => ['ORCHESTRE', [Tariff::FULL, Tariff::REDUCED], '-20 days'],
            'tamra-2' => ['FOSSE', [Tariff::FULL, Tariff::FULL], '-6 days'],
            'cyrano-1' => ['CORBEILLE', [Tariff::FULL], '-30 days'],
        ];
        if (!isset($plan[$key])) {
            return;
        }
        [$zoneCode, $codes, $when] = $plan[$key];
        $zone = $performance->layout()->zoneByCode($zoneCode) ?? throw new LogicException($zoneCode);
        $seats = $zone->isStanding() ? array_fill(0, \count($codes), null) : $writer->pickSeats($performance, $zone, \count($codes), 0.5);
        if (null === $seats) {
            return;
        }
        $lines = [];
        foreach ($seats as $i => $seat) {
            $lines[] = ['zone' => $zone, 'seat' => $seat, 'tariff' => $tariffs[$codes[$i]]];
        }
        $at = min($this->clock->now(), $performance->startsAt())->modify($when);
        $writer->sell($performance, $lines, SalesChannel::ONLINE, max($at, $performance->salesOpenAt()), $customer->id(), null);
    }

    /** ~88 % of tickets scanned between doors and curtain for past performances. */
    private function checkIns(Performance $performance, User $seller): void
    {
        $db = $this->em->getConnection();
        /** @var list<array{id: string}> $tickets */
        $tickets = $db->fetchAllAssociative(
            "SELECT id FROM ticket WHERE performance_id = ? AND status = 'VALID'",
            [$performance->id()->toBinary()],
        );
        $window = max(60, $performance->startsAt()->getTimestamp() - $performance->doorsAt()->getTimestamp());
        foreach ($tickets as $row) {
            if (mt_rand(1, 100) > 88) {
                continue;
            }
            $at = $performance->doorsAt()->modify(sprintf('+%d seconds', mt_rand(0, $window)));
            $db->executeStatement(
                "UPDATE ticket SET status = 'CHECKED_IN', checked_in_at = ?, checked_in_by = ? WHERE id = ?",
                [$at->format('Y-m-d H:i:s'), $seller->id()->toBinary(), $row['id']],
            );
        }
    }

    /**
     * @template T of int|string
     *
     * @param array<T, int> $weights
     *
     * @return T
     */
    private function weighted(array $weights): int|string
    {
        $roll = mt_rand(1, (int) array_sum($weights));
        foreach ($weights as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $value;
            }
        }

        throw new LogicException('Weights must be positive.');
    }
}
