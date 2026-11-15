<?php

declare(strict_types=1);

namespace App\DataFixtures\Demo;

use App\Booking\Domain\Model\Order;
use App\Booking\Domain\Model\Ticket;
use App\Booking\Domain\OrderReferenceGenerator;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\Model\PerformanceSeat;
use App\Catalog\Domain\Model\SeatStatus;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Model\PaymentProvider;
use App\Pricing\Domain\Model\PerformancePrice;
use App\Pricing\Domain\Model\Tariff;
use App\Shared\Domain\SalesChannel;
use App\Venue\Domain\Model\Zone;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Writes demo sales straight into the booking tables (orders, tickets, payments)
 * and marks inventory as sold, mimicking what a finalized checkout produces.
 * Seat choice favours front, centre seats so the seat heatmap looks real.
 */
final class SalesWriter
{
    /** @var array<string, array<string, list<PerformanceSeat>>> perfId => zoneCode => available seats */
    private array $available = [];

    /** @var array<string, array<string, int>> perfId => zoneCode => standing remaining */
    private array $standing = [];

    /** @var array<string, array<string, array<string, int>>> perfId => zoneCode => tariffCode => cents */
    private array $prices = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly OrderReferenceGenerator $references,
        private readonly string $currency,
    ) {
    }

    public function load(Performance $performance): void
    {
        $id = $performance->id()->toRfc4122();
        if (isset($this->available[$id])) {
            return;
        }

        /** @var list<PerformanceSeat> $seats */
        $seats = $this->em->getRepository(PerformanceSeat::class)->findBy(['performance' => $performance]);
        foreach ($seats as $seat) {
            if (SeatStatus::AVAILABLE === $seat->status()) {
                $this->available[$id][$seat->zone()->code()][] = $seat;
            }
        }
        foreach ($performance->layout()->zones() as $zone) {
            if ($zone->isStanding()) {
                $this->standing[$id][$zone->code()] = $zone->capacity() ?? 0;
            }
            $this->available[$id][$zone->code()] ??= [];
        }

        /** @var list<PerformancePrice> $prices */
        $prices = $this->em->getRepository(PerformancePrice::class)->findBy(['performance' => $performance]);
        foreach ($prices as $price) {
            $this->prices[$id][$price->zone()->code()][$price->tariff()->code()] = $price->amountCents();
        }
    }

    public function remaining(Performance $performance, Zone $zone): int
    {
        $id = $performance->id()->toRfc4122();

        return $zone->isStanding()
            ? $this->standing[$id][$zone->code()] ?? 0
            : \count($this->available[$id][$zone->code()] ?? []);
    }

    public function totalRemaining(Performance $performance): int
    {
        return array_sum(array_map(fn (Zone $z): int => $this->remaining($performance, $z), $performance->layout()->zones()));
    }

    public function offers(Performance $performance, Zone $zone, string $tariffCode): bool
    {
        return isset($this->prices[$performance->id()->toRfc4122()][$zone->code()][$tariffCode]);
    }

    /**
     * Picks $quantity adjacent seats in the best row still offering them.
     * Earlier buyers get front/centre seats; $choosiness adds randomness.
     *
     * @return list<PerformanceSeat>|null
     */
    public function pickSeats(Performance $performance, Zone $zone, int $quantity, float $randomness): ?array
    {
        $pool = $this->available[$performance->id()->toRfc4122()][$zone->code()] ?? [];
        $rows = [];
        foreach ($pool as $seat) {
            $rows[$seat->seat()->rowLabel()][$seat->seat()->number()] = $seat;
        }

        $candidates = [];
        $rowIndex = 0;
        foreach ($this->rowOrder($zone) as $row) {
            $seatsInRow = $rows[$row] ?? [];
            ++$rowIndex;
            if (\count($seatsInRow) < $quantity) {
                continue;
            }
            ksort($seatsInRow);
            $numbers = array_keys($seatsInRow);
            $maxNumber = (int) max([0, ...$numbers]);
            for ($i = 0; $i + $quantity <= \count($numbers); ++$i) {
                $block = \array_slice($numbers, $i, $quantity);
                if (end($block) - $block[0] !== $quantity - 1) {
                    continue;
                }
                $centre = abs((($block[0] + end($block)) / 2) - ($maxNumber + 1) / 2) / max(1, $maxNumber);
                $score = $rowIndex + 6 * $centre + $randomness * mt_rand(0, 1000) / 100;
                $candidates[] = [$score, array_map(static fn (int $n): PerformanceSeat => $seatsInRow[$n], $block)];
            }
        }
        if ([] === $candidates) {
            return null;
        }
        usort($candidates, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $candidates[0][1];
    }

    /**
     * @param list<array{zone: Zone, seat: ?PerformanceSeat, tariff: Tariff}> $lines
     */
    public function sell(
        Performance $performance,
        array $lines,
        SalesChannel $channel,
        DateTimeImmutable $at,
        ?Uuid $customerId,
        ?Uuid $sellerId,
        ?string $walkInName = null,
        ?string $note = null,
    ): Order {
        $perfId = $performance->id()->toRfc4122();
        $order = new Order($this->references->next($at), $performance, $channel, $this->currency, $at, $customerId, $sellerId);
        if (null !== $walkInName) {
            $order->forWalkIn($walkInName, null);
        }
        $order->annotate($note);

        $soldSeats = [];
        foreach ($lines as ['zone' => $zone, 'seat' => $seat, 'tariff' => $tariff]) {
            $price = $this->prices[$perfId][$zone->code()][$tariff->code()] ?? throw new LogicException(sprintf('No price for %s/%s', $zone->code(), $tariff->code()));
            $ticket = new Ticket($order, $performance, $zone, $tariff, $price, $seat);
            if (null !== $seat) {
                $soldSeats[] = [$seat, $ticket];
                $this->available[$perfId][$zone->code()] = array_values(array_filter(
                    $this->available[$perfId][$zone->code()],
                    static fn (PerformanceSeat $s): bool => $s !== $seat,
                ));
            } else {
                --$this->standing[$perfId][$zone->code()];
            }
        }

        $order->markPaid($at);
        $provider = 0 === $order->totalCents() ? PaymentProvider::NONE : PaymentProvider::FIXTURE;
        $payment = new Payment($order, $provider, $order->totalCents(), $this->currency, $at, 'fixture_'.$order->reference());
        $payment->succeed($at);
        $this->em->persist($order);
        $this->em->persist($payment);

        $stamp = $at->format('Y-m-d H:i:s');
        foreach ($soldSeats as [$seat, $ticket]) {
            $this->db->executeStatement(
                "UPDATE performance_seat SET status = 'SOLD', ticket_id = ?, updated_at = ? WHERE id = ?",
                [$ticket->id()->toBinary(), $stamp, $seat->id()->toBinary()],
            );
        }

        return $order;
    }

    /** Persist the performance's orders and write standing sold counts back to the stock rows. */
    public function flushStanding(Performance $performance): void
    {
        $this->em->flush();
        foreach ($performance->layout()->zones() as $zone) {
            if (!$zone->isStanding()) {
                continue;
            }
            $sold = ($zone->capacity() ?? 0) - $this->remaining($performance, $zone);
            $this->db->executeStatement(
                'UPDATE performance_zone_stock SET sold_count = ? WHERE performance_id = ? AND zone_id = ?',
                [$sold, $performance->id()->toBinary(), $zone->id()->toBinary()],
            );
        }
    }

    /**
     * Front-to-back order of rows (boxes count as one row each).
     *
     * @return list<string>
     */
    private function rowOrder(Zone $zone): array
    {
        $rows = [];
        foreach ($zone->seats() as $seat) {
            $rows[$seat->rowLabel()] = $seat->y();
        }
        asort($rows);

        return array_map('strval', array_keys($rows));
    }
}
