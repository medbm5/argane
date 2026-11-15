<?php

declare(strict_types=1);

/*
 * Worker for ParallelHoldTest: boots the kernel in a separate PHP process and tries to
 * hold each given seat in turn, every attempt starting on a shared beat so the two
 * workers really collide. Prints `RESULT:` + the JSON list of seat ids it won.
 *
 *   php tests/Concurrency/hold_worker.php <performanceId> <startAtUnixFloat> <seatId,seatId,...>
 */

use App\Booking\Application\Dto\HoldRequest;
use App\Booking\Application\HoldService;
use App\Kernel;
use App\Shared\Domain\Exception\ConflictException;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
// Real, committed transactions: this process must see and lock what the other one writes.
StaticDriver::setKeepStaticConnections(false);

[, $performanceId, $startAt, $seatList] = $argv;
// Debug like the test runner, so the worker reuses its (fresh) compiled container.
$kernel = new Kernel('test', true);
$kernel->boot();
/** @var HoldService $holds */
$holds = $kernel->getContainer()->get(HoldService::class); // @phpstan-ignore symfonyContainer.privateService (public in the test env, see services_test.yaml)

const BEAT_SECONDS = 0.04;
$won = [];
foreach (explode(',', $seatList) as $round => $seatId) {
    $wait = (float) $startAt + $round * BEAT_SECONDS - microtime(true);
    if ($wait > 0) {
        usleep((int) ($wait * 1_000_000));
    }
    try {
        // A fresh buyer each attempt, so "one hold per buyer" never releases a won seat.
        $holds->hold($performanceId, Uuid::v7(), [], new HoldRequest(seatIds: [$seatId]));
        $won[] = $seatId;
    } catch (ConflictException) {
        // Lost the race for this seat.
    }
}

// Tagged so any other output (warnings, cache notices) cannot be mistaken for the result.
echo 'RESULT:', json_encode($won), \PHP_EOL;
