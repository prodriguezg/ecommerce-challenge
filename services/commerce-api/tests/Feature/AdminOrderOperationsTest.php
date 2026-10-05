<?php

namespace Tests\Feature;

use App\Enums\ManualReviewStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ManualReview;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderStateHistory;
use App\Models\Payment;
use App\Models\PaymentStateHistory;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminOrderOperationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_order_list_filters_by_order_payment_date_email_and_number(): void
    {
        $admin = User::factory()->admin()->create();
        $matching = $this->order([
            'status' => OrderStatus::Paid,
            'email' => 'Buyer@Example.test',
            'order_code' => 'ORD-424242',
            'created_at' => '2026-10-04 14:00:00',
        ], PaymentStatus::Succeeded);
        $this->order([
            'status' => OrderStatus::Paid,
            'email' => 'other@example.test',
            'order_code' => 'ORD-999999',
            'created_at' => '2026-10-04 14:00:00',
        ], PaymentStatus::Succeeded);
        $this->order([
            'status' => OrderStatus::PaymentFailed,
            'email' => 'Buyer@Example.test',
            'order_code' => 'ORD-424242-X',
            'created_at' => '2026-10-04 14:00:00',
        ], PaymentStatus::Declined);

        $this->actingAs($admin)->getJson('/api/v1/admin/orders?status=paid&payment_status=succeeded&date_from=2026-10-04&date_to=2026-10-04&email=buyer%40example.test&number=ORD-424242')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $matching->id)
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_admin_order_detail_contains_snapshots_and_complete_timeline(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order(['status' => OrderStatus::ManualReview], PaymentStatus::Succeeded);
        OrderStateHistory::factory()->for($order)->create([
            'from_status' => OrderStatus::Expired,
            'to_status' => OrderStatus::ManualReview,
            'reason_code' => 'late_success_insufficient_stock',
        ]);
        $payment = $order->payment()->firstOrFail();
        PaymentStateHistory::factory()->for($payment)->create([
            'from_status' => PaymentStatus::Processing,
            'to_status' => PaymentStatus::Succeeded,
            'reason_code' => 'provider_succeeded',
        ]);
        Reservation::factory()->for($order)->create();
        ManualReview::factory()->for($order)->create();

        $this->actingAs($admin)->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('customer_email', $order->email)
            ->assertJsonPath('shipping_method_name', $order->shipping_method_name)
            ->assertJsonPath('amounts.total', '16.50')
            ->assertJsonPath('state_history.0.reason', 'late_success_insufficient_stock')
            ->assertJsonPath('payment.history.0.reason', 'provider_succeeded')
            ->assertJsonPath('manual_review.status', ManualReviewStatus::Pending->value)
            ->assertJsonCount(1, 'reservations');
    }

    public function test_pending_review_queue_and_resolution_require_note_and_current_version(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order(['status' => OrderStatus::ManualReview, 'version' => 3], PaymentStatus::Succeeded);
        $review = ManualReview::factory()->for($order)->create();
        $processedOrder = $this->order(['status' => OrderStatus::ReviewProcessed], PaymentStatus::Succeeded);
        ManualReview::factory()->for($processedOrder)->create([
            'status' => ManualReviewStatus::Processed,
            'resolution_note' => 'Done',
            'resolved_by_user_id' => $admin->id,
            'resolved_at' => now(),
        ]);

        $this->actingAs($admin)->getJson('/api/v1/admin/manual-reviews')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $order->id);
        $this->actingAs($admin)->postJson("/api/v1/admin/manual-reviews/{$review->id}/resolve", [
            'note' => '   ',
            'version' => 3,
        ])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->actingAs($admin)->postJson("/api/v1/admin/manual-reviews/{$review->id}/resolve", [
            'note' => 'Resolved with the customer.',
            'version' => 2,
        ])->assertConflict()->assertJsonPath('code', 'manual_review_conflict');

        $this->actingAs($admin)->postJson("/api/v1/admin/manual-reviews/{$review->id}/resolve", [
            'note' => 'Resolved with the customer.',
            'version' => 3,
        ])->assertOk()
            ->assertJsonPath('status', OrderStatus::ReviewProcessed->value)
            ->assertJsonPath('version', 4)
            ->assertJsonPath('manual_review.resolution_note', 'Resolved with the customer.');

        $this->assertDatabaseHas('order_state_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::ReviewProcessed->value,
            'actor_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action_code' => 'manual_review.processed',
            'target_id' => $review->id,
        ]);
    }

    public function test_no_generic_admin_order_edit_route_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order([], PaymentStatus::Requested);

        $this->actingAs($admin)->putJson("/api/v1/admin/orders/{$order->id}", [
            'status' => OrderStatus::Paid->value,
        ])->assertMethodNotAllowed();
    }

    /** @param array<string, mixed> $attributes */
    private function order(array $attributes, PaymentStatus $paymentStatus): Order
    {
        $order = Order::factory()->create($attributes);
        OrderAddress::factory()->for($order)->create();
        Payment::factory()->for($order)->for($order->currency)->create([
            'status' => $paymentStatus,
            'currency_code' => $order->currency_code,
        ]);

        return $order;
    }
}
