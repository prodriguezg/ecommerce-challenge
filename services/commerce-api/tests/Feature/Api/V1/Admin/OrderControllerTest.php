<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Models\Currency;
use App\Models\ManualReview;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_filter_orders_and_read_full_review_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $currency = Currency::factory()->usd()->create();
        $reviewOrder = Order::factory()->for($currency)->create(['status' => OrderStatus::ManualReview]);
        OrderAddress::factory()->for($reviewOrder)->create();
        Payment::factory()->for($reviewOrder)->for($currency)->create();
        $review = ManualReview::factory()->for($reviewOrder)->create();
        $paidOrder = Order::factory()->for($currency)->create(['status' => OrderStatus::Paid]);
        OrderAddress::factory()->for($paidOrder)->create();
        Payment::factory()->for($paidOrder)->for($currency)->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/orders?status=manual_review')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $reviewOrder->id);
        $this->actingAs($admin)->getJson("/api/v1/admin/orders/{$reviewOrder->id}")
            ->assertOk()
            ->assertJsonPath('manual_review.id', $review->id)
            ->assertJsonPath('payment.id', $reviewOrder->payment->id)
            ->assertJsonStructure(['state_history', 'reservations', 'payment' => ['history', 'webhook_events']]);

        $this->assertSame(2, Order::query()->count());
    }
}
