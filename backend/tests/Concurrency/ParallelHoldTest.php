<?php

declare(strict_types=1);

namespace App\Tests\Concurrency;

use App\Catalog\Application\PerformancePublisher;
use App\Catalog\Domain\Model\Performance;
use App\Tests\Support\CatalogFactory;
use App\Tests\Support\SalesFactory;
use App\Venue\Application\Dto\Area;
use App\Venue\Application\Dto\LayoutSpec;
use App\Venue\Application\Dto\ZoneSpec;
use App\Venue\Application\LayoutGenerator;
use App\Venue\Domain\Model\ZoneKind;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Process\Process;

/**
 * Two PHP processes race for the same 50 seats, one seat per 40 ms beat.
 * Exactly one of them must win each seat: never both, never neither.
 *
 * Runs on committed data (DAMA's wrapping transaction is switched off for this class)
 * and cleans up after itself.
 */
final class ParallelHoldTest extends KernelTestCase
{
    private const int ROUNDS = 50;

    /** @var list<string> */
    private array $cleanup = [];

    public static function setUpBeforeClass(): void
    {
        StaticDriver::setKeepStaticConnections(false);
    }

    public static function tearDownAfterClass(): void
    {
        StaticDriver::setKeepStaticConnections(true);
    }

    protected function setUp(): void
    {
        Clock::set(new NativeClock());
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        foreach ($this->cleanup as $sql) {
            $connection->executeStatement($sql);
        }
        parent::tearDown();
    }

    private function seedFiftySeats(): Performance
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $venue = CatalogFactory::venue('race');
        $layout = (new LayoutGenerator(new NativeClock()))->generate($venue, new LayoutSpec('Course', [
            new ZoneSpec('PARTERRE', 'Parterre', ZoneKind::SEATED, new Area(100, 100, 800, 500), rowLabels: ['A', 'B', 'C', 'D', 'E'], seatsPerRow: 10),
        ]));
        $event = CatalogFactory::event($venue, true, 'Course aux places');
        $performance = Performance::schedule($event, $layout, new DateTimeImmutable('+30 days'), new DateTimeImmutable('-1 day'));
        foreach ([$venue, $layout, $event, $performance] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        self::getContainer()->get(PerformancePublisher::class)->publish($performance, openSales: true);

        $p = 'UNHEX(\''.bin2hex($performance->id()->toBinary()).'\')';
        $l = 'UNHEX(\''.bin2hex($layout->id()->toBinary()).'\')';
        $this->cleanup = [
            "DELETE FROM hold WHERE performance_id = {$p}",
            "DELETE FROM performance_seat WHERE performance_id = {$p}",
            "DELETE FROM performance_zone_stock WHERE performance_id = {$p}",
            "DELETE FROM performance WHERE id = {$p}",
            'DELETE FROM event WHERE id = UNHEX(\''.bin2hex($event->id()->toBinary()).'\')',
            "DELETE s FROM seat s JOIN zone z ON z.id = s.zone_id WHERE z.layout_id = {$l}",
            "DELETE FROM zone WHERE layout_id = {$l}",
            "DELETE FROM hall_layout WHERE id = {$l}",
            'DELETE FROM venue WHERE id = UNHEX(\''.bin2hex($venue->id()->toBinary()).'\')',
        ];

        return $performance;
    }

    public function testExactlyOneBuyerWinsEachSeat(): void
    {
        $performance = $this->seedFiftySeats();
        $seats = array_values(SalesFactory::seatIds(self::getContainer()->get(Connection::class), $performance));
        self::assertCount(self::ROUNDS, $seats);
        shuffle($seats);

        $startAt = sprintf('%.6F', microtime(true) + 3.0); // both kernels booted by then
        $workers = [];
        foreach ([1, 2] as $_) {
            $process = new Process([\PHP_BINARY, __DIR__.'/hold_worker.php', $performance->id()->toRfc4122(), $startAt, implode(',', $seats)]);
            $process->setTimeout(120);
            $process->start();
            $workers[] = $process;
        }

        $wins = [];
        foreach ($workers as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $lines = array_filter(explode(\PHP_EOL, trim($process->getOutput())));
            $wins[] = json_decode((string) end($lines), true, flags: \JSON_THROW_ON_ERROR);
        }
        [$first, $second] = $wins;

        self::assertSame([], array_values(array_intersect($first, $second)), 'no seat may be won twice');
        self::assertEqualsCanonicalizing($seats, [...$first, ...$second], 'every seat is won exactly once');
        self::assertSame(self::ROUNDS, (int) self::getContainer()->get(Connection::class)->fetchOne(
            "SELECT COUNT(*) FROM performance_seat WHERE performance_id = ? AND status = 'HELD'",
            [$performance->id()->toBinary()],
        ));
    }
}
