<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\ManualReviewStatus;
use App\Enums\OrderStatus;
use App\Models\Currency;
use App\Models\ManualReview;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ManualReviewControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_lists_pending_reviews_and_customer_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        [$pendingOrder, $pending] = $this->reviewOrder();
        [$processedOrder, $processed] = $this->reviewOrder();
        $processed->forceFill(['status' => ManualReviewStatus::Processed])->save();
        $processedOrder->forceFill(['status' => OrderStatus::ReviewProcessed])->save();

        $this->actingAs($admin)->getJson('/api/v1/admin/manual-reviews')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $pendingOrder->id)
            ->assertJsonPath('items.0.manual_review.id', $pending->id);
        $this->actingAs($customer)->getJson('/api/v1/admin/manual-reviews')->assertForbidden();
    }

    public function test_resolution_requires_note_and_current_version(): void
    {
        $admin = User::factory()->admin()->create();
        [$order, $review] = $this->reviewOrder();

        $this->actingAs($admin)->postJson("/api/v1/admin/manual-reviews/{$review->id}/resolve", [
            'version' => $order->version,
        ])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->actingAs($admin)->postJson("/api/v1/admin/manual-reviews/{$review->id}/resolve", [
            'note' => '   ',
            'version' => $order->version,
        ])->assertUnprocessable()->assertJsonValidationErrors('note');

        $this->actingAs($admin)->postJson("/api/v1/admin/manual-reviews/{$review->id}/resolve", [
            'note' => 'Resolved with the customer externally.',
            'version' => $order->version + 1,
        ])->assertConflict()->assertJsonPath('code', 'manual_review_conflict');

        $this->assertSame(ManualReviewStatus::Pending, $review->fresh()->status);
    }

    public function test_admin_resolution_records_note_actor_history_and_audit(): void
    {
        $admin = User::factory()->admin()->create();
        [$order, $review] = $this->reviewOrder();

        $this->actingAs($admin)->postJson("/api/v1/admin/manual-reviews/{$review->id}/resolve", [
            'note' => 'Resolved with the customer externally.',
            'version' => $order->version,
        ])->assertOk()
            ->assertJsonPath('status', OrderStatus::ReviewProcessed->value)
            ->assertJsonPath('manual_review.status', ManualReviewStatus::Processed->value);

        $this->assertDatabaseHas('manual_reviews', [
            'id' => $review->id,
            'status' => ManualReviewStatus::Processed->value,
            'resolution_note' => 'Resolved with the customer externally.',
            'resolved_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('order_state_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::ReviewProcessed->value,
            'actor_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action_code' => 'manual_review.processed',
            'target_id' => $review->id,
        ]);
    }

    /** @return array{Order, ManualReview} */
    private function reviewOrder(): array
    {
        $currency = Currency::query()->first() ?? Currency::factory()->usd()->create();
        $order = Order::factory()->for($currency)->create(['status' => OrderStatus::ManualReview]);
        OrderAddress::factory()->for($order)->create();
        Payment::factory()->for($order)->for($currency)->create();
        $review = ManualReview::factory()->for($order)->create();

        return [$order, $review];
    }
}
