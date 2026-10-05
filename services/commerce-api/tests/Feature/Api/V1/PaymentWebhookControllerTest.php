<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\ManualReview;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['api.checkout.payment_webhook_token' => 'test-webhook-token']);
    }

    public function test_returns_401_without_the_configured_bearer_token(): void
    {
        $this->postJson('/api/v1/payments/webhooks/mock', $this->payload((string) Str::ulid(), (string) Str::ulid()))
            ->assertUnauthorized();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_on_time_success_deducts_stock_once_and_duplicate_events_are_idempotent(): void
    {
        [$order, $payment, $reservation, $inventory] = $this->paymentState(stock: 5, quantity: 2);
        $payload = $this->payload($payment->provider_payment_id, $payment->id);

        $this->send($payload)->assertNoContent();
        $this->send($payload)->assertNoContent();
        $this->send([...$payload, 'event_id' => (string) Str::ulid()])->assertNoContent();
        $this->send($this->payload($payment->provider_payment_id, $payment->id, 'declined', 'PAYMENT_DECLINED'))->assertNoContent();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(ReservationStatus::Consumed, $reservation->fresh()->status);
        $this->assertSame(3, $inventory->fresh()->stock_on_hand);
        $this->assertDatabaseCount('webhook_events', 3);
        $this->assertDatabaseCount('payment_state_histories', 1);
    }

    public function test_decline_releases_reservation_and_late_success_recovers_with_fresh_stock(): void
    {
        [$order, $payment, $reservation, $inventory] = $this->paymentState(stock: 5, quantity: 2);

        $this->send($this->payload($payment->provider_payment_id, $payment->id, 'declined', 'PAYMENT_DECLINED'))->assertNoContent();

        $this->assertSame(OrderStatus::PaymentFailed, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Declined, $payment->fresh()->status);
        $this->assertSame(ReservationStatus::Released, $reservation->fresh()->status);
        $this->assertSame(5, $inventory->fresh()->stock_on_hand);

        $this->send($this->payload($payment->provider_payment_id, $payment->id))->assertNoContent();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(3, $inventory->fresh()->stock_on_hand);
    }

    public function test_late_success_with_insufficient_stock_creates_one_manual_review(): void
    {
        [$order, $payment, $reservation, $inventory] = $this->paymentState(
            stock: 1,
            quantity: 2,
            orderStatus: OrderStatus::Expired,
            reservationStatus: ReservationStatus::Expired,
        );
        $payload = $this->payload($payment->provider_payment_id, $payment->id);

        $this->send($payload)->assertNoContent();
        $this->send([...$payload, 'event_id' => (string) Str::ulid()])->assertNoContent();

        $this->assertSame(OrderStatus::ManualReview, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(1, $inventory->fresh()->stock_on_hand);
        $this->assertSame('late_success_insufficient_stock', ManualReview::query()->sole()->reason_code);
        $this->assertDatabaseCount('manual_reviews', 1);
    }

    public function test_same_event_id_with_a_different_payload_returns_409_without_second_effect(): void
    {
        [$order, $payment, $reservation, $inventory] = $this->paymentState(stock: 5, quantity: 1);
        $payload = $this->payload($payment->provider_payment_id, $payment->id);

        $this->send($payload)->assertNoContent();
        $this->send([...$payload, 'provider_code' => 'DIFFERENT_SAFE_CODE'])
            ->assertConflict()
            ->assertJsonPath('code', 'payment_event_conflict');

        $this->assertSame(4, $inventory->fresh()->stock_on_hand);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(ReservationStatus::Consumed, $reservation->fresh()->status);
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_late_success_correlates_an_initiation_failure_without_a_stored_provider_id(): void
    {
        [$order, $payment, $reservation, $inventory] = $this->paymentState(stock: 3, quantity: 1);
        $order->forceFill(['status' => OrderStatus::PaymentFailed])->save();
        $payment->forceFill(['status' => PaymentStatus::InitiationFailed, 'provider_payment_id' => null])->save();
        $reservation->forceFill(['status' => ReservationStatus::Released])->save();
        $providerPaymentId = (string) Str::ulid();

        $this->send($this->payload($providerPaymentId, $payment->id))->assertNoContent();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame($providerPaymentId, $payment->fresh()->provider_payment_id);
        $this->assertSame(2, $inventory->fresh()->stock_on_hand);
    }

    /** @return array{Order, Payment, Reservation, Inventory} */
    private function paymentState(
        int $stock,
        int $quantity,
        OrderStatus $orderStatus = OrderStatus::AwaitingPayment,
        ReservationStatus $reservationStatus = ReservationStatus::Active,
    ): array {
        $currency = Currency::factory()->usd()->create();
        $product = Product::factory()->for($currency)->create();
        $inventory = Inventory::factory()->for($product)->create(['stock_on_hand' => $stock]);
        $order = Order::factory()->for($currency)->create(['status' => $orderStatus]);
        $reservation = Reservation::factory()->for($order)->create([
            'status' => $reservationStatus,
            'expires_at' => $reservationStatus === ReservationStatus::Active ? now()->addMinute() : now()->subMinute(),
        ]);
        ReservationItem::factory()->for($reservation)->for($inventory)->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
        $payment = Payment::factory()->for($order)->for($currency)->create([
            'provider_payment_id' => (string) Str::ulid(),
            'status' => PaymentStatus::Processing,
        ]);

        return [$order, $payment, $reservation, $inventory];
    }

    /** @return array{event_id: string, commerce_payment_id: string, provider_payment_id: string, outcome: string, provider_code: string, occurred_at: string} */
    private function payload(
        string $providerPaymentId,
        string $commercePaymentId,
        string $outcome = 'succeeded',
        string $providerCode = 'PAYMENT_SUCCEEDED',
    ): array {
        return [
            'event_id' => (string) Str::ulid(),
            'commerce_payment_id' => $commercePaymentId,
            'provider_payment_id' => $providerPaymentId,
            'outcome' => $outcome,
            'provider_code' => $providerCode,
            'occurred_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** @param array<string, string> $payload */
    private function send(array $payload): TestResponse
    {
        return $this->withToken('test-webhook-token')->postJson('/api/v1/payments/webhooks/mock', $payload);
    }
}
