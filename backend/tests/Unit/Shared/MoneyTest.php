<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Money;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testNormalizesCurrencyToLowercase(): void
    {
        self::assertSame('mad', Money::of(100, 'MAD')->currency);
    }

    public function testRejectsInvalidCurrency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of(100, 'dirham');
    }

    public function testArithmetic(): void
    {
        $a = Money::of(15000, 'mad');
        $b = Money::of(4550, 'mad');

        self::assertSame(19550, $a->add($b)->amountCents);
        self::assertSame(10450, $a->subtract($b)->amountCents);
        self::assertSame(45000, $a->multiply(3)->amountCents);
        self::assertTrue($a->greaterThan($b));
        self::assertTrue(Money::zero('mad')->isZero());
        self::assertTrue($b->subtract($a)->isNegative());
    }

    public function testSum(): void
    {
        $total = Money::sum([Money::of(100, 'mad'), Money::of(250, 'mad')], 'mad');

        self::assertTrue($total->equals(Money::of(350, 'mad')));
    }

    public function testRefusesMixedCurrencies(): void
    {
        $this->expectException(LogicException::class);
        Money::of(100, 'mad')->add(Money::of(100, 'eur'));
    }

    public function testSerializesToMinorUnits(): void
    {
        self::assertSame('{"amountCents":12345,"currency":"mad"}', json_encode(Money::of(12345, 'mad')));
    }
}
