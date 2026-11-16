<?php

declare(strict_types=1);

namespace App\Tests\Functional\Studio;

use App\Catalog\Domain\InventoryRepository;
use App\Catalog\Domain\Model\Performance;
use App\Identity\Domain\Model\Role;
use App\Identity\Domain\Model\User;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\Orders;
use App\Tests\Support\SalesFactory;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/** The Studio's "Salle" page: the organizer switches seats of the plan on and off. */
final class HallSeatsTest extends WebTestCase
{
    use ActsAsUser;

    private KernelBrowser $client;
    private User $organizer;
    private Performance $upcoming;
    private Performance $past;
    private Connection $db;

    protected function setUp(): void
    {
        Clock::set(new MockClock(CatalogFactory::NOW));
        $this->client = self::createClient();
        self::stubClerk($this->client);
        $container = self::getContainer();
        $this->organizer = self::createUser('user_hall_boss', Role::ORGANIZER);
        $this->upcoming = SalesFactory::onSale($container, '2026-12-12 19:00:00');

        // A date already played on the same plan.
        $em = $container->get(EntityManagerInterface::class);
        $this->past = CatalogFactory::performance($this->upcoming->event(), $this->upcoming->layout(), '2026-11-01 19:00:00');
        $em->persist($this->past);
        $em->flush();
        $container->get(InventoryRepository::class)->generate($this->past, new DateTimeImmutable('2026-10-01 09:00:00'));

        $this->db = $container->get(Connection::class);
    }

    /** @return array<string, string> seat label → plan seat id */
    private function planSeats(): array
    {
        $this->client->jsonRequest('GET', '/api/studio/layouts/'.$this->upcoming->layout()->id(), [], self::bearer($this->organizer));
        self::assertResponseIsSuccessful();
        $layout = (array) json_decode((string) $this->client->getResponse()->getContent(), true);
        $ids = [];
        foreach ((array) $layout['seats'] as $seat) {
            \assert(\is_array($seat));
            $ids[(string) $seat['label']] = (string) $seat['id'];
        }

        return $ids;
    }

    /**
     * @param list<string> $labels
     *
     * @return array<mixed>
     */
    private function setBlocked(array $labels, bool $blocked, ?User $as = null, int $status = 200): array
    {
        $plan = $this->planSeats();
        $this->client->jsonRequest('PUT', '/api/studio/layouts/'.$this->upcoming->layout()->id().'/seats', [
            'seatIds' => array_map(static fn (string $l): string => $plan[$l] ?? $l, $labels),
            'blocked' => $blocked,
        ], self::bearer($as ?? $this->organizer));
        self::assertResponseStatusCodeSame($status, (string) $this->client->getResponse()->getContent());

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function statusOn(Performance $performance, string $label): string
    {
        return (string) $this->db->fetchOne(
            "SELECT ps.status FROM performance_seat ps JOIN seat s ON s.id = ps.seat_id
              WHERE ps.performance_id = ? AND CONCAT(s.row_label, '-', s.number) = ?",
            [$performance->id()->toBinary(), $label],
            [ParameterType::BINARY],
        );
    }

    public function testDeactivatingSeatsTakesThemOffSaleOnUpcomingDatesOnly(): void
    {
        Orders::paid(self::getContainer(), $this->upcoming, self::createUser('user_hall_fan'), ['A-3']);

        $result = $this->setBlocked(['A-1', 'A-2', 'A-3'], true);

        self::assertSame(2, $result['seatsUpdated'], 'A-1 and A-2 on the upcoming date');
        self::assertSame(1, $result['seatsKept'], 'A-3 is sold there: never taken back');
        $blocked = array_column(array_filter((array) $result['layout']['seats'], static fn (array $s): bool => $s['blocked']), 'label');
        self::assertSame([], array_diff(['A-1', 'A-2', 'A-3'], $blocked), 'the plan itself is updated');

        self::assertSame('BLOCKED', $this->statusOn($this->upcoming, 'A-1'));
        self::assertSame('SOLD', $this->statusOn($this->upcoming, 'A-3'));
        self::assertSame('AVAILABLE', $this->statusOn($this->past, 'A-1'), 'past dates stay as they were');
    }

    public function testReactivatingPutsSeatsBackOnSale(): void
    {
        $this->setBlocked(['A-1'], true);

        $result = $this->setBlocked(['A-1'], false);

        self::assertSame(1, $result['seatsUpdated']);
        self::assertSame('AVAILABLE', $this->statusOn($this->upcoming, 'A-1'));
    }

    public function testOnlySeatsOfThisPlanAndOnlyTheOrganizer(): void
    {
        $this->setBlocked(['not-a-seat'], true, status: 422);
        $this->setBlocked(['A-1'], true, self::createUser('user_hall_staff', Role::BOX_OFFICE), 403);
    }
}
