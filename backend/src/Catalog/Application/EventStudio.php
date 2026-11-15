<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Dto\EventInput;
use App\Catalog\Application\Dto\PerformanceInput;
use App\Catalog\Application\Query\StudioCatalogQueries;
use App\Catalog\Application\Query\View\StudioEventView;
use App\Catalog\Domain\EventRepository;
use App\Catalog\Domain\Model\Event;
use App\Catalog\Domain\Model\EventCategory;
use App\Catalog\Domain\Model\Performance;
use App\Catalog\Domain\PerformanceRepository;
use App\Shared\Domain\Exception\BusinessRuleException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\VenueTime;
use App\Venue\Domain\Model\HallLayout;
use App\Venue\Domain\VenueRepository;
use DateTimeImmutable;
use LogicException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/** Studio use cases for events and their performances. */
final readonly class EventStudio
{
    public function __construct(
        private EventRepository $events,
        private PerformanceRepository $performances,
        private VenueRepository $venues,
        private PerformancePublisher $publisher,
        private StudioCatalogQueries $queries,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<StudioEventView>
     */
    public function list(): array
    {
        return $this->queries->events($this->clock->now());
    }

    public function view(string $eventId): StudioEventView
    {
        return $this->queries->event($eventId, $this->clock->now()) ?? throw NotFoundException::of('Spectacle', $eventId);
    }

    public function create(EventInput $input, Uuid $organizerId): StudioEventView
    {
        $venue = $this->venues->main() ?? throw new LogicException('No venue configured.');
        $slug = Event::slugify($input->title);
        for ($i = 2; $this->events->slugExists($slug); ++$i) {
            $slug = Event::slugify($input->title).'-'.$i;
        }
        $event = new Event($venue, trim($input->title), EventCategory::from($input->category), $input->durationMinutes, $organizerId, $this->clock->now(), $slug);
        $this->describe($event, $input);

        return $this->view($event->id()->toRfc4122());
    }

    public function update(string $eventId, EventInput $input): StudioEventView
    {
        $this->describe($this->event($eventId), $input);

        return $this->view($eventId);
    }

    public function publish(string $eventId): StudioEventView
    {
        $event = $this->event($eventId);
        $event->publish($this->clock->now());
        $this->events->save($event);

        return $this->view($eventId);
    }

    public function archive(string $eventId): StudioEventView
    {
        $event = $this->event($eventId);
        $event->archive();
        $this->events->save($event);

        return $this->view($eventId);
    }

    public function schedule(string $eventId, PerformanceInput $input): StudioEventView
    {
        $event = $this->event($eventId);
        [$startsAt, $doorsAt, $opensAt, $closesAt] = $this->times($input);
        $performance = Performance::schedule($event, $this->layout($input->layoutId), $startsAt, $opensAt);
        $performance->reschedule($startsAt, $doorsAt, $opensAt, $closesAt);
        $this->performances->save($performance);

        return $this->view($eventId);
    }

    public function reschedule(string $performanceId, PerformanceInput $input): StudioEventView
    {
        $performance = $this->performance($performanceId);
        [$startsAt, $doorsAt, $opensAt, $closesAt] = $this->times($input);
        $performance->reschedule($startsAt, $doorsAt, $opensAt, $closesAt);
        if ($performance->layout()->id()->toRfc4122() !== strtolower($input->layoutId)) {
            $performance->changeLayout($this->layout($input->layoutId));
        }
        $this->performances->save($performance);

        return $this->view($performance->event()->id()->toRfc4122());
    }

    /** Publishing generates the inventory; optionally opens sales at once. */
    public function publishPerformance(string $performanceId, bool $openSales): StudioEventView
    {
        $performance = $this->performance($performanceId);
        $this->publisher->publish($performance, $openSales);

        return $this->view($performance->event()->id()->toRfc4122());
    }

    public function openSales(string $performanceId): StudioEventView
    {
        $performance = $this->performance($performanceId);
        $performance->openSales($this->clock->now());
        $this->performances->save($performance);

        return $this->view($performance->event()->id()->toRfc4122());
    }

    public function closeSales(string $performanceId): StudioEventView
    {
        $performance = $this->performance($performanceId);
        $performance->closeSales();
        $this->performances->save($performance);

        return $this->view($performance->event()->id()->toRfc4122());
    }

    private function describe(Event $event, EventInput $input): void
    {
        $event->describe(
            trim($input->title),
            null === $input->subtitle ? null : trim($input->subtitle),
            EventCategory::from($input->category),
            $input->description,
            $input->durationMinutes,
            $input->ageRating,
            $input->posterPath,
        );
        $this->events->save($event);
    }

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable, DateTimeImmutable, DateTimeImmutable}
     */
    private function times(PerformanceInput $input): array
    {
        $startsAt = VenueTime::fromVenueLocal($input->startsAt);
        $opensAt = VenueTime::fromVenueLocal($input->salesOpenAt);
        $closesAt = null === $input->salesCloseAt || '' === $input->salesCloseAt ? $startsAt : VenueTime::fromVenueLocal($input->salesCloseAt);
        if ($opensAt >= $startsAt) {
            throw BusinessRuleException::because('SALES_OPEN_AFTER_START', 'L’ouverture des ventes doit précéder la représentation.');
        }

        return [$startsAt, $startsAt->modify(sprintf('-%d minutes', $input->doorsMinutesBefore)), $opensAt, $closesAt];
    }

    private function event(string $id): Event
    {
        return (Uuid::isValid($id) ? $this->events->find(Uuid::fromString($id)) : null) ?? throw NotFoundException::of('Spectacle', $id);
    }

    private function performance(string $id): Performance
    {
        return (Uuid::isValid($id) ? $this->performances->find(Uuid::fromString($id)) : null) ?? throw NotFoundException::of('Représentation', $id);
    }

    private function layout(string $id): HallLayout
    {
        return (Uuid::isValid($id) ? $this->venues->findLayout(Uuid::fromString($id)) : null) ?? throw NotFoundException::of('Configuration', $id);
    }
}
