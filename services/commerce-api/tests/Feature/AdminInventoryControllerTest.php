<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->product = Product::factory()->create(['currency_id' => Currency::factory()->usd()->create()->getKey()]);
        $this->inventory = Inventory::factory()->create(['product_id' => $this->product->getKey(), 'stock_on_hand' => 10]);
        $this->actingAs($this->admin);
    }

    public function test_adjustment_is_atomic_immutable_and_audited(): void
    {
        $this->postJson("/api/v1/admin/products/{$this->product->getKey()}/inventory-adjustments", [
            'on_hand' => 14,
            'reason' => 'stock_received',
        ])->assertCreated()->assertJsonPath('available', 14);

        $adjustment = $this->inventory->adjustments()->firstOrFail();
        $this->assertSame(10, $adjustment->previous_quantity);
        $this->assertSame(14, $adjustment->new_quantity);
        $this->assertSame(4, $adjustment->delta);
        $this->expectException(\LogicException::class);
        InventoryAdjustment::unguarded(fn (): bool => $adjustment->update(['note' => 'changed']));
    }

    public function test_other_adjustment_requires_a_note(): void
    {
        $this->postJson("/api/v1/admin/products/{$this->product->getKey()}/inventory-adjustments", [
            'on_hand' => 10,
            'reason' => 'other',
        ])->assertUnprocessable()->assertJsonValidationErrors('note');
    }

    public function test_stock_cannot_fall_below_active_reservations(): void
    {
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Active]);
        ReservationItem::factory()->create([
            'reservation_id' => $reservation->getKey(),
            'inventory_id' => $this->inventory->getKey(),
            'product_id' => $this->product->getKey(),
            'quantity' => 6,
        ]);

        $this->postJson("/api/v1/admin/products/{$this->product->getKey()}/inventory-adjustments", [
            'on_hand' => 5,
            'reason' => 'correction',
        ])->assertConflict()->assertJsonPath('code', 'inventory_reservation_floor');

        $this->assertSame(10, $this->inventory->fresh()->stock_on_hand);
        $this->assertCount(0, $this->inventory->adjustments);
    }
}
