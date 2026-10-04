<?php

namespace Tests\Feature\Models;

use App\Enums\OrderStatus;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_product_graph_exposes_currency_inventory_and_reservations(): void
    {
        $currency = Currency::factory()->create();
        $product = Product::factory()->for($currency)->create();
        $inventory = Inventory::factory()->for($product)->create();
        $reservation = Reservation::factory()->create();
        $item = ReservationItem::factory()->for($reservation)->for($product)->for($inventory)->create();

        $this->assertTrue($product->currency->is($currency));
        $this->assertTrue($product->inventory->is($inventory));
        $this->assertTrue($reservation->items->first()->is($item));
    }

    public function test_order_graph_exposes_snapshots_payment_and_enum_casts(): void
    {
        $currency = Currency::factory()->create();
        $order = Order::factory()->for($currency)->create();
        $line = OrderLine::factory()->for($order)->for($currency)->create();
        $payment = Payment::factory()->for($order)->for($currency)->create();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertTrue($order->lines->first()->is($line));
        $this->assertTrue($order->payment->is($payment));
        $this->assertSame('16.5000', $payment->amount);
    }

    public function test_order_line_snapshots_cannot_be_modified(): void
    {
        $line = OrderLine::factory()->create();

        $this->expectException(LogicException::class);
        $line->product_name = 'Changed';
        $line->save();
    }
}
