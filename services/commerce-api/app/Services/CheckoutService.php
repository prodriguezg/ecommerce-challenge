<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\CheckoutConflictException;
use App\Models\CheckoutIdempotency;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderLine;
use App\Models\OrderStateHistory;
use App\Models\Payment;
use App\Models\PaymentStateHistory;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\ShippingMethod;
use App\Models\User;
use App\ValueObjects\CheckoutOutcome;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckoutService
{
    public function __construct(
        private readonly PaymentProvider $paymentProvider,
        private readonly ApplicationSettings $settings,
    ) {}

    /** @param array<string, mixed> $input */
    public function checkout(array $input, ?User $customer): CheckoutOutcome
    {
        $canonical = $this->canonicalInput($input);
        $contextHash = hash('sha256', $customer === null ? 'guest:'.$canonical['email'] : 'customer:'.$customer->id);
        $requestHash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
        $guestToken = null;

        /** @var array{CheckoutIdempotency, Order, ?string, bool} $created */
        $created = DB::transaction(function () use ($canonical, $customer, $contextHash, $requestHash, &$guestToken): array {
            $idempotency = CheckoutIdempotency::query()
                ->where('context_hash', $contextHash)
                ->where('idempotency_key', $canonical['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($idempotency instanceof CheckoutIdempotency) {
                if (! hash_equals($idempotency->request_hash, $requestHash)) {
                    throw new CheckoutConflictException('idempotency_conflict', 'The idempotency key was already used with a different checkout request.');
                }

                $order = $idempotency->order()->firstOrFail();

                return [$idempotency, $order, null, false];
            }

            if ($customer === null && User::query()->where('normalized_email', $canonical['email'])->exists()) {
                throw new CheckoutConflictException('login_required', 'Sign in to check out with this registered email address.');
            }

            $idempotency = CheckoutIdempotency::query()->create([
                'context_hash' => $contextHash,
                'idempotency_key' => $canonical['idempotency_key'],
                'request_hash' => $requestHash,
            ]);
            $order = $this->createOrder($canonical, $customer, $guestToken);
            $idempotency->forceFill(['order_id' => $order->id])->save();

            return [$idempotency, $order, $guestToken, true];
        }, 3);

        [$idempotency, $order, $issuedGuestToken, $isNew] = $created;

        if (! $isNew) {
            return new CheckoutOutcome($order, $customer === null, null, (int) ($idempotency->response_status ?? 202));
        }

        try {
            $payment = $order->payment()->firstOrFail();
            $acceptance = $this->paymentProvider->create($payment, $canonical['payment_test_number']);

            DB::transaction(function () use ($payment, $acceptance, $idempotency, $customer): void {
                $payment->forceFill([
                    'provider_payment_id' => $acceptance['provider_payment_id'],
                    'status' => PaymentStatus::Processing,
                ])->save();
                $this->paymentHistory($payment, PaymentStatus::Requested, PaymentStatus::Processing, 'provider_accepted');
                $idempotency->forceFill(['response_status' => 202])->save();

                $cart = $customer?->cart()->first();
                if ($cart !== null) {
                    $cart->items()->delete();
                }
            });

            $order->refresh();

            return new CheckoutOutcome($order, $customer === null, $issuedGuestToken, 202);
        } catch (Throwable) {
            DB::transaction(function () use ($order, $idempotency): void {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                $payment = $lockedOrder->payment()->lockForUpdate()->firstOrFail();
                $payment->forceFill(['status' => PaymentStatus::InitiationFailed, 'error_code' => 'initiation_failed'])->save();
                $this->paymentHistory($payment, PaymentStatus::Requested, PaymentStatus::InitiationFailed, 'provider_unavailable');
                $lockedOrder->forceFill(['status' => OrderStatus::PaymentFailed, 'version' => $lockedOrder->version + 1])->save();
                $this->orderHistory($lockedOrder, OrderStatus::AwaitingPayment, OrderStatus::PaymentFailed, 'payment_initiation_failed');
                $lockedOrder->reservations()->where('status', ReservationStatus::Active->value)->update(['status' => ReservationStatus::Released->value]);
                $idempotency->forceFill(['response_status' => 500])->save();
            });

            return new CheckoutOutcome($order->fresh(), $customer === null, $issuedGuestToken, 500);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{email: string, shipping_address: array<string, string|null>, shipping_method_id: string, lines: array<string, int>, payment_test_number: string, idempotency_key: string}
     */
    private function canonicalInput(array $input): array
    {
        $lines = [];

        foreach ($input['lines'] as $line) {
            $productId = (string) $line['product_id'];
            $lines[$productId] = ($lines[$productId] ?? 0) + (int) $line['quantity'];
        }

        ksort($lines);

        foreach ($lines as $quantity) {
            if ($quantity > 999) {
                throw new CheckoutConflictException('inventory_unavailable', 'An aggregated line quantity exceeds the allowed maximum.');
            }
        }

        return [
            'email' => (string) $input['email'],
            'shipping_address' => $input['shipping_address'],
            'shipping_method_id' => (string) $input['shipping_method_id'],
            'lines' => $lines,
            'payment_test_number' => (string) $input['payment_test_number'],
            'idempotency_key' => (string) $input['idempotency_key'],
        ];
    }

    /** @param array{email: string, shipping_address: array<string, string|null>, shipping_method_id: string, lines: array<string, int>, payment_test_number: string, idempotency_key: string} $input */
    private function createOrder(array $input, ?User $customer, ?string &$guestToken): Order
    {
        $databaseNow = CarbonImmutable::parse((string) DB::scalar('SELECT CURRENT_TIMESTAMP'))->utc();
        $inventories = Inventory::query()
            ->whereIn('product_id', array_keys($input['lines']))
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');
        $products = Product::query()->withTrashed()
            ->whereIn('id', array_keys($input['lines']))
            ->with(['currency', 'tax'])
            ->get()
            ->keyBy('id');
        $shipping = ShippingMethod::query()->with(['currency', 'tax'])->findOrFail($input['shipping_method_id']);

        if ($products->count() !== count($input['lines']) || $inventories->count() !== count($input['lines'])) {
            throw new CheckoutConflictException('inventory_unavailable', 'One or more products are unavailable.');
        }

        $currency = $shipping->currency;
        $subtotal = BigDecimal::zero();
        $productTax = BigDecimal::zero();
        $lineSnapshots = [];

        foreach ($input['lines'] as $productId => $quantity) {
            /** @var Product $product */
            $product = $products->get($productId);
            /** @var Inventory $inventory */
            $inventory = $inventories->get($productId);
            $reserved = (int) $inventory->reservationItems()
                ->whereHas('reservation', fn ($query) => $query
                    ->where('status', ReservationStatus::Active->value)
                    ->where('expires_at', '>', $databaseNow))
                ->sum('quantity');

            if ($product->trashed() || $product->currency_id !== $currency->id || $quantity > $inventory->stock_on_hand - $reserved) {
                throw new CheckoutConflictException('inventory_unavailable', 'One or more products do not have enough available inventory.');
            }

            $lineSubtotal = BigDecimal::of((string) $product->price)->multipliedBy($quantity);
            $taxRate = $product->tax_id === null ? '0' : $product->tax->rate;
            $taxAmount = $this->taxFor($lineSubtotal, $taxRate, $currency->minor_units);
            $subtotal = $subtotal->plus($lineSubtotal);
            $productTax = $productTax->plus($taxAmount);
            $lineSnapshots[] = compact('product', 'inventory', 'quantity', 'lineSubtotal', 'taxRate', 'taxAmount');
        }

        $shippingAmount = BigDecimal::of((string) $shipping->amount);
        $shippingTaxRate = $shipping->tax_id === null ? '0' : $shipping->tax->rate;
        $shippingTax = $this->taxFor($shippingAmount, $shippingTaxRate, $currency->minor_units);
        $grandTotal = $subtotal->plus($productTax)->plus($shippingAmount)->plus($shippingTax);
        $sequence = $this->nextOrderSequence();

        if ($customer === null) {
            $guestToken = bin2hex(random_bytes(32));
        }

        $order = Order::query()->create([
            'sequence_number' => $sequence,
            'order_code' => (string) config('api.checkout.order_number_prefix').$sequence,
            'customer_user_id' => $customer?->id,
            'email' => $input['email'],
            'normalized_email' => $input['email'],
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'status' => OrderStatus::AwaitingPayment,
            'subtotal' => $this->storage($subtotal),
            'product_tax' => $this->storage($productTax),
            'shipping_amount' => $this->storage($shippingAmount),
            'shipping_tax' => $this->storage($shippingTax),
            'grand_total' => $this->storage($grandTotal),
            'shipping_method_name' => $shipping->name,
            'guest_token_hash' => $guestToken === null ? null : hash('sha256', $guestToken),
            'guest_token_expires_at' => $guestToken === null ? null : $databaseNow->addDays((int) config('api.checkout.guest_order_link_days')),
            'version' => 1,
        ]);

        foreach ($lineSnapshots as $snapshot) {
            /** @var Product $product */
            $product = $snapshot['product'];
            $line = new OrderLine;
            $line->forceFill([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $snapshot['quantity'],
                'weight_kg' => $product->weight_kg,
                'unit_price' => $product->price,
                'line_subtotal' => $this->storage($snapshot['lineSubtotal']),
                'tax_name' => $product->tax_id === null ? null : $product->tax->name,
                'tax_rate' => $snapshot['taxRate'],
                'tax_amount' => $this->storage($snapshot['taxAmount']),
                'line_total' => $this->storage($snapshot['lineSubtotal']->plus($snapshot['taxAmount'])),
                'currency_id' => $currency->id,
                'currency_code' => $currency->code,
            ])->save();
        }

        $address = new OrderAddress;
        $address->forceFill([
            'order_id' => $order->id,
            'recipient_name' => $input['shipping_address']['name'],
            'line_1' => $input['shipping_address']['line1'],
            'line_2' => $input['shipping_address']['line2'] ?? null,
            'city' => $input['shipping_address']['city'],
            'region' => $input['shipping_address']['region'],
            'postal_code' => $input['shipping_address']['postal_code'],
            'country_code' => $input['shipping_address']['country'],
            'phone' => $input['shipping_address']['phone'],
            'email' => $input['email'],
        ])->save();
        $this->orderHistory($order, null, OrderStatus::AwaitingPayment, 'checkout_created');

        $reservation = Reservation::query()->create([
            'order_id' => $order->id,
            'status' => ReservationStatus::Active,
            'expires_at' => $databaseNow->addSeconds($this->reservationTimeout()),
        ]);

        foreach ($lineSnapshots as $snapshot) {
            ReservationItem::query()->create([
                'reservation_id' => $reservation->id,
                'product_id' => $snapshot['product']->id,
                'inventory_id' => $snapshot['inventory']->id,
                'quantity' => $snapshot['quantity'],
            ]);
        }

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'idempotency_key' => 'payment-'.$order->id,
            'status' => PaymentStatus::Requested,
            'amount' => $this->storage($grandTotal),
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
        ]);
        $this->paymentHistory($payment, null, PaymentStatus::Requested, 'checkout_created');

        return $order;
    }

    private function reservationTimeout(): int
    {
        return $this->settings->effective(ApplicationSettings::ReservationTimeoutSeconds)['value'];
    }

    private function nextOrderSequence(): int
    {
        $row = DB::table('order_sequences')->where('name', 'orders')->lockForUpdate()->first();
        $sequence = (int) $row->next_value;
        DB::table('order_sequences')->where('name', 'orders')->update(['next_value' => $sequence + 1]);

        return $sequence;
    }

    private function taxFor(BigDecimal $amount, string $rate, int $minorUnits): BigDecimal
    {
        return $amount->multipliedBy($rate)->dividedBy(100, $minorUnits, RoundingMode::HalfUp);
    }

    private function storage(BigDecimal $amount): string
    {
        return (string) $amount->toScale(4, RoundingMode::Unnecessary);
    }

    private function orderHistory(Order $order, ?OrderStatus $from, OrderStatus $to, string $reason): void
    {
        $history = new OrderStateHistory;
        $history->forceFill(['order_id' => $order->id, 'from_status' => $from, 'to_status' => $to, 'reason_code' => $reason, 'metadata' => []])->save();
    }

    private function paymentHistory(Payment $payment, ?PaymentStatus $from, PaymentStatus $to, string $reason): void
    {
        $history = new PaymentStateHistory;
        $history->forceFill(['payment_id' => $payment->id, 'from_status' => $from, 'to_status' => $to, 'reason_code' => $reason, 'metadata' => []])->save();
    }
}
