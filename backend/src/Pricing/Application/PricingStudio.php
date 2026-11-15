<?php

declare(strict_types=1);

namespace App\Pricing\Application;

use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\PerformanceRepository;
use App\Pricing\Application\Dto\PriceGridView;
use App\Pricing\Application\Dto\TariffInput;
use App\Pricing\Application\Dto\TariffView;
use App\Pricing\Domain\Model\Tariff;
use App\Pricing\Domain\PriceRepository;
use App\Pricing\Domain\TariffRepository;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\SalesChannel;
use App\Venue\Domain\Model\Venue;
use App\Venue\Domain\VenueRepository;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/** Studio use cases for tariffs and price grids. */
final readonly class PricingStudio
{
    public function __construct(
        private TariffRepository $tariffs,
        private PriceRepository $prices,
        private PerformanceRepository $performances,
        private VenueRepository $venues,
        private PriceGridService $grids,
        #[Autowire('%app.currency%')]
        private string $currency,
    ) {
    }

    /**
     * @return list<TariffView>
     */
    public function tariffs(): array
    {
        $all = $this->tariffs->ofVenue($this->venue(), activeOnly: false);
        usort($all, static fn (Tariff $a, Tariff $b): int => [$a->sortOrder(), $a->label()] <=> [$b->sortOrder(), $b->label()]);

        return array_map(TariffView::of(...), $all);
    }

    public function createTariff(TariffInput $input): TariffView
    {
        $venue = $this->venue();
        if (null !== $this->tariffs->findByCode($venue, $input->code)) {
            throw ConflictException::because('TARIFF_CODE_TAKEN', sprintf('Le code %s existe déjà.', $input->code));
        }
        $tariff = new Tariff($venue, $input->code, $input->label, self::channels($input), $input->allowedRoles, $input->minQuantity, $input->maxQuantity, $input->requiresProofAtEntry, $input->isFree, $input->description);
        $tariff->update($input->label, self::channels($input), $input->allowedRoles, $input->minQuantity, $input->maxQuantity, $input->requiresProofAtEntry, $input->isFree, $input->description, $input->sortOrder);
        $this->tariffs->save($tariff);

        return TariffView::of($tariff);
    }

    public function updateTariff(string $id, TariffInput $input): TariffView
    {
        $tariff = $this->tariff($id);
        $tariff->update($input->label, self::channels($input), $input->allowedRoles, $input->minQuantity, $input->maxQuantity, $input->requiresProofAtEntry, $input->isFree, $input->description, $input->sortOrder);
        $this->tariffs->save($tariff);

        return TariffView::of($tariff);
    }

    /** Inactive tariffs disappear from sale at once; prices already paid are untouched. */
    public function activate(string $id, bool $active): TariffView
    {
        $tariff = $this->tariff($id);
        $tariff->activate($active);
        $this->tariffs->save($tariff);

        return TariffView::of($tariff);
    }

    public function grid(string $performanceId): PriceGridView
    {
        $performance = $this->performance($performanceId);
        $zones = array_map(static fn ($z): array => [
            'id' => $z->id()->toRfc4122(),
            'code' => $z->code(),
            'name' => $z->name(),
            'kind' => $z->kind()->value,
        ], $performance->layout()->zones());
        $cells = array_map(static fn ($p): array => [
            'zoneId' => $p->zone()->id()->toRfc4122(),
            'tariffId' => $p->tariff()->id()->toRfc4122(),
            'amountCents' => $p->amountCents(),
        ], $this->prices->ofPerformance($performance));

        return new PriceGridView($performance->id()->toRfc4122(), strtoupper($this->currency), $zones, $this->tariffs(), $cells);
    }

    /**
     * @param list<array{zoneId: string, tariffId: string, amountCents: int|null}> $cells
     */
    public function setGrid(string $performanceId, array $cells): PriceGridView
    {
        $this->grids->setGrid($this->performance($performanceId), $cells);

        return $this->grid($performanceId);
    }

    /** Copies this grid to the event's other performances (zones matched by code). */
    public function applyToEvent(string $performanceId): int
    {
        return $this->grids->applyToAll($this->performance($performanceId));
    }

    /**
     * @return list<SalesChannel>
     */
    private static function channels(TariffInput $input): array
    {
        return array_map(static fn (string $c): SalesChannel => SalesChannel::from($c), $input->channels);
    }

    private function venue(): Venue
    {
        return $this->venues->main() ?? throw new LogicException('No venue configured.');
    }

    private function tariff(string $id): Tariff
    {
        return (Uuid::isValid($id) ? $this->tariffs->find(Uuid::fromString($id)) : null) ?? throw NotFoundException::of('Tarif', $id);
    }

    private function performance(string $id): Performance
    {
        return (Uuid::isValid($id) ? $this->performances->find(Uuid::fromString($id)) : null) ?? throw NotFoundException::of('Représentation', $id);
    }
}
