<?php

declare(strict_types=1);

namespace App\Booking\Application;

use App\Booking\Application\Dto\HoldRequest;
use App\Booking\Application\Dto\HoldView;
use App\Booking\Domain\HoldRepository;
use App\Booking\Domain\Model\Hold;
use App\Booking\Domain\Model\StandingHoldLine;
use App\Booking\Domain\OrderRepository;
use App\Catalog\Domain\InventoryRepository;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\PerformanceRepository;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\SalesChannel;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Places a temporary hold on seats and standing places while the buyer pays.
 *
 * One transaction: release the buyer's previous hold on this performance, claim seats
 * with a single conditional UPDATE, lock and check standing stock, then persist the hold.
 * Any seat or place lost to someone else rolls everything back (409 with the ids).
 */
final readonly class HoldService
{
    public function __construct(
        private PerformanceRepository $performances,
        private HoldRepository $holds,
        private OrderRepository $orders,
        private InventoryRepository $inventory,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        #[Autowire('%app.max_seats_per_order%')]
        private int $maxPlaces,
        #[Autowire('%app.hold_ttl_seconds%')]
        private int $ttlSeconds,
    ) {
    }

    /**
     * @param list<string> $roles the caller's reachable roles
     */
    public function hold(string $performanceId, Uuid $userId, array $roles, HoldRequest $request): HoldView
    {
        $now = $this->clock->now();
        $channel = SalesChannel::from($request->channel);
        if (SalesChannel::BOX_OFFICE === $channel && !\in_array('ROLE_BOX_OFFICE', $roles, true)) {
            throw BusinessRuleException::because('CHANNEL_FORBIDDEN', 'Le guichet est réservé au personnel.', [], 403);
        }

        $performance = $this->onSale($performanceId, $channel, $now);
        $seatIds = array_map(static fn (string $id): Uuid => Uuid::fromString($id), array_values(array_unique($request->seatIds)));
        $standing = $this->standingRequest($performance, $request->standing);
        $total = \count($seatIds) + array_sum(array_column($standing, 'quantity'));
        if (0 === $total) {
            throw BusinessRuleException::because('EMPTY_HOLD', 'Choisissez au moins une place.');
        }
        if ($total > $this->maxPlaces) {
            throw BusinessRuleException::because('TOO_MANY_PLACES', sprintf('%d places maximum par commande.', $this->maxPlaces), ['max' => $this->maxPlaces]);
        }

        $holdId = Uuid::v7();
        $expiresAt = $now->modify(sprintf('+%d seconds', $this->ttlSeconds));
        $previous = $this->holds->activeOf($userId, $performance);
        $connection = $this->em->getConnection();

        $connection->beginTransaction();
        try {
            foreach ($previous as $old) {
                $this->inventory->releaseSeats($old->id(), $now);
                $connection->executeStatement("UPDATE hold SET status = 'RELEASED' WHERE id = ? AND status = 'ACTIVE'", [$old->id()->toBinary()]);
            }

            $lost = $this->inventory->holdSeats($performance->id(), $seatIds, $holdId, $expiresAt, $now);
            if ([] !== $lost) {
                throw ConflictException::because('SEATS_UNAVAILABLE', \count($lost) > 1 ? 'Ces places viennent d’être prises.' : 'Cette place vient d’être prise.', ['seatIds' => $lost]);
            }

            foreach ($standing as ['zone' => $zone, 'quantity' => $quantity]) {
                $stock = $this->inventory->lockStanding($performance->id(), $zone->id(), $now);
                if (null === $stock || $stock['remaining'] < $quantity) {
                    throw ConflictException::because('STANDING_UNAVAILABLE', sprintf('Il ne reste que %d places en %s.', $stock['remaining'] ?? 0, $zone->name()), ['zoneId' => $zone->id()->toRfc4122(), 'remaining' => $stock['remaining'] ?? 0]);
                }
            }

            $hold = new Hold($performance, $userId, $channel, $expiresAt, $now, $holdId);
            foreach ($standing as ['zone' => $zone, 'quantity' => $quantity]) {
                new StandingHoldLine($hold, $zone, $quantity);
            }
            $this->em->persist($hold);
            $this->em->flush();
            $connection->commit();
        } catch (RetryableException) {
            $connection->rollBack();
            throw ConflictException::because('SEATS_BUSY', 'Ces places sont très demandées en ce moment, réessayez.');
        } catch (Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        foreach ($previous as $old) {
            $old->release();
        }

        return HoldView::of($hold, $this->inventory->seatsOfHold($holdId), $now);
    }

    public function view(string $holdId, Uuid $userId): HoldView
    {
        $hold = $this->owned($holdId, $userId);

        return HoldView::of($hold, $hold->isActive($this->clock->now()) ? $this->inventory->seatsOfHold($hold->id()) : [], $this->clock->now());
    }

    /** Gives the places back. Idempotent; only the owner may release. */
    public function release(string $holdId, Uuid $userId): void
    {
        $hold = $this->owned($holdId, $userId);
        $now = $this->clock->now();
        $this->em->wrapInTransaction(function () use ($hold, $now): void {
            $this->inventory->releaseSeats($hold->id(), $now);
            $hold->release();
        });
    }

    /** Housekeeping for the scheduler: expires due holds and their unpaid orders, frees seats. */
    public function expireDue(): int
    {
        $now = $this->clock->now();

        return $this->em->wrapInTransaction(function () use ($now): int {
            $this->inventory->releaseExpiredSeats($now);
            $this->orders->expireUnpaid($now);

            return $this->holds->expireDue($now);
        });
    }

    private function owned(string $holdId, Uuid $userId): Hold
    {
        $hold = Uuid::isValid($holdId) ? $this->holds->find(Uuid::fromString($holdId)) : null;
        if (null === $hold || !$hold->userId()->equals($userId)) {
            throw NotFoundException::of('Réservation', $holdId);
        }

        return $hold;
    }

    private function onSale(string $performanceId, SalesChannel $channel, DateTimeImmutable $now): Performance
    {
        $performance = Uuid::isValid($performanceId) ? $this->performances->find(Uuid::fromString($performanceId)) : null;
        if (null === $performance || !$performance->isPublished()) {
            throw NotFoundException::of('Représentation', $performanceId);
        }
        $open = SalesChannel::BOX_OFFICE === $channel ? $performance->isOnSaleAtCounter($now) : $performance->isOnSale($now);
        if (!$open) {
            throw BusinessRuleException::because('NOT_ON_SALE', 'Cette représentation n’est pas en vente.', ['status' => $performance->displayStatus($now)]);
        }

        return $performance;
    }

    /**
     * @param list<array{zoneId: string, quantity: int}> $lines
     *
     * @return list<array{zone: \App\Venue\Domain\Model\Zone, quantity: int}>
     */
    private function standingRequest(Performance $performance, array $lines): array
    {
        $zones = [];
        foreach ($performance->layout()->zones() as $zone) {
            $zones[$zone->id()->toRfc4122()] = $zone;
        }
        $merged = [];
        foreach ($lines as $line) {
            $zone = $zones[strtolower($line['zoneId'])] ?? null;
            if (null === $zone || !$zone->isStanding()) {
                throw BusinessRuleException::because('UNKNOWN_ZONE', 'Zone debout inconnue pour cette représentation.', ['zoneId' => $line['zoneId']]);
            }
            $merged[$zone->id()->toRfc4122()] = [
                'zone' => $zone,
                'quantity' => ($merged[$zone->id()->toRfc4122()]['quantity'] ?? 0) + $line['quantity'],
            ];
        }

        return array_values($merged);
    }
}
