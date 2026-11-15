<?php

declare(strict_types=1);

namespace App\Booking\Infrastructure;

use App\Booking\Domain\OrderReferenceGenerator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Gap-tolerant per-year counter in `order_sequence`. The increment uses MySQL's
 * LAST_INSERT_ID(expr) trick so it is atomic without a SELECT … FOR UPDATE,
 * and works inside or outside the caller's transaction.
 */
final readonly class SequenceOrderReferenceGenerator implements OrderReferenceGenerator
{
    public const string PREFIX = 'ARG';

    public function __construct(private Connection $connection)
    {
    }

    public function next(DateTimeImmutable $now): string
    {
        $year = (int) $now->format('y');
        $this->connection->executeStatement(
            'INSERT INTO order_sequence (year, next_value) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value + 1)',
            [$year],
        );
        $value = (int) $this->connection->fetchOne('SELECT LAST_INSERT_ID()');

        return self::format($year, $value);
    }

    public static function format(int $year, int $value): string
    {
        return sprintf('%s-%02d-%06d', self::PREFIX, $year, $value);
    }
}
