<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;

/**
 * Money in integer minor units (centimes) with an ISO 4217 currency code.
 * Never use floats for money.
 */
final readonly class Money implements JsonSerializable
{
    public string $currency;

    private function __construct(
        public int $amountCents,
        string $currency,
    ) {
        $currency = strtolower(trim($currency));
        if (1 !== preg_match('/^[a-z]{3}$/', $currency)) {
            throw new InvalidArgumentException(sprintf('Invalid currency code "%s".', $currency));
        }
        $this->currency = $currency;
    }

    public static function of(int $amountCents, string $currency): self
    {
        return new self($amountCents, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountCents + $other->amountCents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountCents - $other->amountCents, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amountCents * $factor, $this->currency);
    }

    /**
     * @param iterable<self> $amounts
     */
    public static function sum(iterable $amounts, string $currency): self
    {
        $total = self::zero($currency);
        foreach ($amounts as $amount) {
            $total = $total->add($amount);
        }

        return $total;
    }

    public function isZero(): bool
    {
        return 0 === $this->amountCents;
    }

    public function isNegative(): bool
    {
        return $this->amountCents < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amountCents === $other->amountCents;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountCents > $other->amountCents;
    }

    /**
     * @return array{amountCents: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['amountCents' => $this->amountCents, 'currency' => $this->currency];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new LogicException(sprintf('Currency mismatch: %s vs %s.', $this->currency, $other->currency));
        }
    }
}
