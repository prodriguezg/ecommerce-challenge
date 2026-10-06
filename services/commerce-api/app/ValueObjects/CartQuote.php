<?php

namespace App\ValueObjects;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class CartQuote
{
    /**
     * @param  list<array{product_id: string, name: string, quantity: int, unit_price: string, tax_rate: string, available: bool, stock_limit: int, adjustment: ?string}>  $lines
     */
    public function __construct(
        private array $lines,
        private string $currencyCode,
        private int $minorUnits,
        private string $shippingAmount = '0',
        private string $shippingTaxRate = '0',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $subtotal = BigDecimal::zero();
        $productTax = BigDecimal::zero();
        $responseLines = [];
        $requiresConfirmation = false;

        foreach ($this->lines as $line) {
            $lineSubtotal = BigDecimal::of($line['unit_price'])->multipliedBy($line['quantity']);
            $lineTax = $this->taxFor($lineSubtotal, $line['tax_rate']);
            $subtotal = $subtotal->plus($lineSubtotal);
            $productTax = $productTax->plus($lineTax);
            $requiresConfirmation = $requiresConfirmation || ! $line['available'] || $line['adjustment'] !== null;

            $responseLine = [
                'product_id' => $line['product_id'],
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'unit_price' => $this->rounded($line['unit_price']),
                'line_subtotal' => $this->rounded((string) $lineSubtotal),
                'currency' => $this->currencyCode,
                'available' => $line['available'],
                'stock_limit' => $line['stock_limit'],
            ];

            if ($line['adjustment'] !== null) {
                $responseLine['adjustment'] = $line['adjustment'];
            }

            $responseLines[] = $responseLine;
        }

        $shipping = BigDecimal::of($this->shippingAmount);
        $shippingTax = $this->taxFor($shipping, $this->shippingTaxRate);
        $tax = $productTax->plus($shippingTax);
        $total = $subtotal->plus($shipping)->plus($tax);

        return [
            'lines' => $responseLines,
            'subtotal' => $this->rounded((string) $subtotal),
            'product_tax' => $this->rounded((string) $productTax),
            'shipping_tax' => $this->rounded((string) $shippingTax),
            'tax' => $this->rounded((string) $tax),
            'shipping' => $this->rounded((string) $shipping),
            'total' => $this->rounded((string) $total),
            'currency' => $this->currencyCode,
            'requires_confirmation' => $requiresConfirmation,
        ];
    }

    private function taxFor(BigDecimal $amount, string $rate): BigDecimal
    {
        return $amount
            ->multipliedBy($rate)
            ->dividedBy(100, $this->minorUnits, RoundingMode::HalfUp);
    }

    private function rounded(string $amount): string
    {
        return (string) BigDecimal::of($amount)->toScale($this->minorUnits, RoundingMode::HalfUp);
    }
}
