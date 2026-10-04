<?php

namespace App\ValueObjects;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final readonly class Money
{
    private BigDecimal $value;

    public function __construct(string|int $amount, public string $currencyCode)
    {
        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new InvalidArgumentException('Currency code must be a three-letter uppercase ISO code.');
        }

        $this->value = BigDecimal::of($amount)->toScale(4, RoundingMode::Unnecessary);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self((string) $this->value->plus($other->value), $this->currencyCode);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }

        return new self((string) $this->value->multipliedBy($quantity), $this->currencyCode);
    }

    public function amount(): string
    {
        return (string) $this->value;
    }

    public function roundedAmount(int $minorUnits): string
    {
        return (string) $this->value->toScale($minorUnits, RoundingMode::HalfUp);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currencyCode !== $other->currencyCode) {
            throw new InvalidArgumentException('Money values must use the same currency.');
        }
    }
}
