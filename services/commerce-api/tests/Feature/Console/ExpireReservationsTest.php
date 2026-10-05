<?php

namespace Tests\Feature\Console;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExpireReservationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_claims_a_bounded_batch_and_repeated_runs_are_idempotent(): void
    {
        $firstOrder = Order::factory()->create();
        $secondOrder = Order::factory()->create();
        $futureOrder = Order::factory()->create();
        $first = Reservation::factory()->for($firstOrder)->create(['expires_at' => now()->subMinutes(2)]);
        $second = Reservation::factory()->for($secondOrder)->create(['expires_at' => now()->subMinute()]);
        $future = Reservation::factory()->for($futureOrder)->create(['expires_at' => now()->addMinute()]);

        $this->artisan('reservations:expire', ['--limit' => 1])
            ->expectsOutput('Expired 1 reservation(s).')
            ->assertSuccessful();

        $this->assertSame(ReservationStatus::Expired, $first->fresh()->status);
        $this->assertSame(OrderStatus::Expired, $firstOrder->fresh()->status);
        $this->assertSame(ReservationStatus::Active, $second->fresh()->status);

        $this->artisan('reservations:expire', ['--limit' => 10])
            ->expectsOutput('Expired 1 reservation(s).')
            ->assertSuccessful();
        $this->artisan('reservations:expire', ['--limit' => 10])
            ->expectsOutput('Expired 0 reservation(s).')
            ->assertSuccessful();

        $this->assertSame(ReservationStatus::Expired, $second->fresh()->status);
        $this->assertSame(ReservationStatus::Active, $future->fresh()->status);
        $this->assertDatabaseCount('order_state_histories', 2);
    }

    public function test_stale_claim_is_recovered_but_fresh_claim_is_not_stolen(): void
    {
        config(['api.checkout.reservation_claim_ttl_seconds' => 60]);
        $stale = Reservation::factory()->create([
            'expires_at' => now()->subMinutes(2),
            'claim_token' => str_repeat('a', 64),
            'claimed_at' => now()->subMinutes(2),
        ]);
        $fresh = Reservation::factory()->create([
            'expires_at' => now()->subMinutes(2),
            'claim_token' => str_repeat('b', 64),
            'claimed_at' => now(),
        ]);

        $this->artisan('reservations:expire')->assertSuccessful();

        $this->assertSame(ReservationStatus::Expired, $stale->fresh()->status);
        $this->assertSame(ReservationStatus::Active, $fresh->fresh()->status);
    }
}
