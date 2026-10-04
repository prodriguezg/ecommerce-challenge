<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_adds_and_multiplies_without_binary_floating_point(): void
    {
        $total = (new Money('10.1250', 'USD'))
            ->multiply(2)
            ->add(new Money('5.0000', 'USD'));

        $this->assertSame('25.2500', $total->amount());
    }

    public function test_rounds_half_up_to_currency_minor_units(): void
    {
        $money = new Money('1.0050', 'USD');

        $this->assertSame('1.01', $money->roundedAmount(2));
    }

    public function test_rejects_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Money('1.0000', 'USD'))->add(new Money('1.0000', 'EUR'));
    }
}
