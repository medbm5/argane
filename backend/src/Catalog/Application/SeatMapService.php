<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Query\SeatMapQueries;
use App\Catalog\Application\Query\View\AvailabilityView;
use App\Catalog\Application\Query\View\SeatMapView;
use App\Catalog\Application\Query\View\SeatMapZoneView;
use App\Catalog\Application\Query\View\ZonePriceView;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\PerformanceRepository;
use App\Pricing\Application\TariffEligibilityPolicy;
use App\Pricing\Domain\Model\PerformancePrice;
use App\Pricing\Domain\Model\Tariff;
use App\Pricing\Domain\PriceRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\SalesChannel;
use App\Venue\Domain\Model\HallLayout;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/** Builds the seat map (geometry + prices for the caller) and the compact availability. */
final readonly class SeatMapService
{
    public function __construct(
        private PerformanceRepository $performances,
        private PriceRepository $prices,
        private TariffEligibilityPolicy $eligibility,
        private SeatMapQueries $queries,
        #[Autowire('%app.currency%')]
        private string $currency,
        #[Autowire('%app.max_seats_per_order%')]
        private int $maxSeatsPerOrder,
    ) {
    }

    /**
     * @param list<string> $roles the caller's reachable roles
     */
    public function seatMap(string $performanceId, SalesChannel $channel, array $roles): SeatMapView
    {
        $performance = $this->published($performanceId);
        $layout = $performance->layout();

        $byZone = [];
        $offerable = [];
        foreach ($this->prices->ofPerformance($performance) as $price) {
            $tariff = $price->tariff();
            $offerable[$tariff->id()->toRfc4122()] ??= [] !== $this->eligibility->offerable([$tariff], $channel, $roles);
            if ($offerable[$tariff->id()->toRfc4122()]) {
                $byZone[$price->zone()->id()->toRfc4122()][] = $price;
            }
        }

        $zones = [];
        foreach ($layout->zones() as $zone) {
            $zonePrices = $byZone[$zone->id()->toRfc4122()] ?? [];
            usort($zonePrices, static fn (PerformancePrice $a, PerformancePrice $b): int => [$a->tariff()->sortOrder(), $a->amountCents()] <=> [$b->tariff()->sortOrder(), $b->amountCents()]);
            $zones[] = new SeatMapZoneView(
                $zone->id()->toRfc4122(),
                $zone->code(),
                $zone->name(),
                $zone->kind()->value,
                $zone->colorToken(),
                $zone->sortOrder(),
                $zone->shape(),
                $zone->isStanding() ? $zone->capacity() : null,
                array_map(self::priceView(...), $zonePrices),
            );
        }
        usort($zones, static fn (SeatMapZoneView $a, SeatMapZoneView $b): int => $a->sortOrder <=> $b->sortOrder);

        return new SeatMapView(
            $performance->id()->toRfc4122(),
            $layout->id()->toRfc4122(),
            $layout->name(),
            $channel->value,
            strtoupper($this->currency),
            ['width' => HallLayout::VIEWBOX_WIDTH, 'height' => HallLayout::VIEWBOX_HEIGHT],
            HallLayout::STAGE,
            $this->maxSeatsPerOrder,
            $zones,
            $this->queries->seats($performance->id()),
        );
    }

    public function availability(string $performanceId, DateTimeImmutable $now, ?Uuid $userId): AvailabilityView
    {
        $performance = $this->published($performanceId);
        $states = $this->queries->seatStates($performance->id(), $now, $userId);
        $standing = $this->queries->standing($performance->id(), $now);
        $standingLeft = array_sum(array_map(static fn ($s): int => $s->remaining, $standing));

        return new AvailabilityView(
            $performance->id()->toRfc4122(),
            $performance->displayStatus($now),
            $performance->isOnSale($now),
            $states['free'] + $standingLeft,
            $states['held'],
            $states['mine'],
            $states['sold'],
            $states['blocked'],
            $standing,
        );
    }

    private function published(string $performanceId): Performance
    {
        $performance = Uuid::isValid($performanceId) ? $this->performances->find(Uuid::fromString($performanceId)) : null;
        if (null === $performance || !$performance->isPublished()) {
            throw NotFoundException::of('Représentation', $performanceId);
        }

        return $performance;
    }

    private static function priceView(PerformancePrice $price): ZonePriceView
    {
        $tariff = $price->tariff();

        return new ZonePriceView(
            $tariff->id()->toRfc4122(),
            $tariff->code(),
            $tariff->label(),
            $tariff->description(),
            $price->amountCents(),
            $tariff->minQuantity(),
            $tariff->maxQuantity(),
            $tariff->requiresProofAtEntry(),
            Tariff::INVITATION === $tariff->code(),
        );
    }
}
