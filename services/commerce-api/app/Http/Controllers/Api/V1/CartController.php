<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MergeCartRequest;
use App\Http\Requests\Api\V1\QuoteCartRequest;
use App\Http\Requests\Api\V1\SetCartItemRequest;
use App\Http\Requests\Api\V1\SetCartShippingMethodRequest;
use App\Http\Responses\ProblemDetails;
use App\Models\Cart;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\ValueObjects\CartQuote;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $cart = $this->customerCart($this->customer($request));
        Gate::authorize('view', $cart);

        return $this->quoteResponse($this->cartQuantities($cart), $cart->shippingMethod);
    }

    public function setItem(SetCartItemRequest $request, string $product): JsonResponse
    {
        $cart = $this->customerCart($this->customer($request));
        Gate::authorize('update', $cart);
        $catalogProduct = $this->products([$product])->get($product);

        if (! $catalogProduct instanceof Product || $catalogProduct->trashed()) {
            abort(404);
        }

        $quantity = (int) $request->validated('quantity');
        $stockLimit = $catalogProduct->availableStock();

        if ($stockLimit === 0 || $quantity > $stockLimit) {
            throw ValidationException::withMessages([
                'quantity' => ["The quantity may not exceed the current stock limit of {$stockLimit}."],
            ]);
        }

        if ($catalogProduct->currency_id !== $cart->currency_id) {
            throw ValidationException::withMessages([
                'product' => ['The product currency does not match the cart currency.'],
            ]);
        }

        $cart->items()->updateOrCreate(
            ['product_id' => $catalogProduct->id],
            ['quantity' => $quantity],
        );

        return $this->quoteResponse($this->cartQuantities($cart), $cart->shippingMethod);
    }

    public function deleteItem(Request $request, string $product): JsonResponse
    {
        $cart = $this->customerCart($this->customer($request));
        Gate::authorize('update', $cart);
        $cart->items()->where('product_id', $product)->delete();

        return $this->quoteResponse($this->cartQuantities($cart), $cart->shippingMethod);
    }

    public function setShippingMethod(SetCartShippingMethodRequest $request): JsonResponse
    {
        $cart = $this->customerCart($this->customer($request));
        Gate::authorize('update', $cart);
        $shippingMethodId = $request->validated('shipping_method_id');
        $shippingMethod = $shippingMethodId === null
            ? null
            : ShippingMethod::query()->with(['currency', 'tax'])->findOrFail($shippingMethodId);

        if ($shippingMethod !== null && $shippingMethod->currency_id !== $cart->currency_id) {
            throw ValidationException::withMessages([
                'shipping_method_id' => ['The shipping method currency does not match the cart currency.'],
            ]);
        }

        $cart->shippingMethod()->associate($shippingMethod);
        $cart->save();

        return $this->quoteResponse($this->cartQuantities($cart), $shippingMethod);
    }

    public function merge(MergeCartRequest $request): JsonResponse
    {
        $cart = $this->customerCart($this->customer($request));
        Gate::authorize('update', $cart);
        $guestQuantities = $this->aggregateLines($request->validated('lines'));
        $products = $this->products(array_keys($guestQuantities));
        $adjustments = [];

        DB::transaction(function () use ($cart, $guestQuantities, $products, &$adjustments): void {
            foreach ($guestQuantities as $productId => $guestQuantity) {
                $product = $products->get($productId);

                if (! $product instanceof Product || $product->trashed()) {
                    $adjustments[$productId] = 'The product is unavailable and was not merged.';

                    continue;
                }

                if ($product->currency_id !== $cart->currency_id) {
                    $adjustments[$productId] = 'The product currency does not match the cart and it was not merged.';

                    continue;
                }

                $existingQuantity = (int) $cart->items()
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->value('quantity');
                $requestedQuantity = $existingQuantity + $guestQuantity;
                $mergedQuantity = min($requestedQuantity, $product->availableStock());

                if ($mergedQuantity === 0) {
                    $adjustments[$productId] = 'The product is out of stock and was not merged.';

                    continue;
                }

                $cart->items()->updateOrCreate(
                    ['product_id' => $productId],
                    ['quantity' => $mergedQuantity],
                );

                if ($mergedQuantity < $requestedQuantity) {
                    $adjustments[$productId] = "Quantity was capped at the current stock limit of {$mergedQuantity}.";
                }
            }
        });

        $responseQuantities = $this->cartQuantities($cart);
        foreach ($guestQuantities as $productId => $quantity) {
            if (isset($adjustments[$productId]) && ! isset($responseQuantities[$productId])) {
                $responseQuantities[$productId] = $quantity;
            }
        }

        return $this->quoteResponse($responseQuantities, $cart->shippingMethod, $adjustments)
            ->header('X-Cart-Merge-Acknowledged', 'true');
    }

    public function quote(QuoteCartRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isCustomer()) {
            return ProblemDetails::response(
                $request,
                403,
                'Forbidden',
                'Administrators cannot own or manipulate customer carts.',
                'forbidden',
            );
        }

        $shippingMethodId = $request->validated('shipping_method_id');
        $shippingMethod = $shippingMethodId === null
            ? null
            : ShippingMethod::query()->with(['currency', 'tax'])->findOrFail($shippingMethodId);

        return $this->quoteResponse(
            $this->aggregateLines($request->validated('lines')),
            $shippingMethod,
        );
    }

    private function customer(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    private function customerCart(User $user): Cart
    {
        $currency = Currency::query()->where('is_base', true)->firstOrFail();

        return Cart::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['currency_id' => $currency->id],
        );
    }

    /** @return array<string, int> */
    private function cartQuantities(Cart $cart): array
    {
        return $cart->items()
            ->pluck('quantity', 'product_id')
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function aggregateLines(mixed $lines): array
    {
        $quantities = [];

        foreach ((array) $lines as $line) {
            $productId = (string) $line['product_id'];
            $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) $line['quantity'];
        }

        return $quantities;
    }

    /**
     * @param  list<string>  $productIds
     * @return Collection<string, Product>
     */
    private function products(array $productIds): Collection
    {
        return Product::query()
            ->withTrashed()
            ->whereIn('id', $productIds)
            ->with(['currency', 'tax', 'inventory'])
            ->withSum('activeReservationItems as reserved_quantity', 'quantity')
            ->get()
            ->keyBy(fn (Product $product): string => (string) $product->getKey());
    }

    /**
     * @param  array<string, int>  $quantities
     * @param  array<string, string>  $adjustments
     */
    private function quoteResponse(array $quantities, ?ShippingMethod $shippingMethod, array $adjustments = []): JsonResponse
    {
        $products = $this->products(array_keys($quantities));
        $currency = $this->quoteCurrency($products, $shippingMethod);
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product instanceof Product) {
                $lines[] = [
                    'product_id' => $productId,
                    'name' => 'Unavailable product',
                    'quantity' => $quantity,
                    'unit_price' => '0',
                    'tax_rate' => '0',
                    'available' => false,
                    'stock_limit' => 0,
                    'adjustment' => $adjustments[$productId] ?? 'The product is unavailable.',
                ];

                continue;
            }

            if ($product->currency_id !== $currency->id) {
                throw ValidationException::withMessages([
                    'lines' => ['All cart products and shipping must use the same currency.'],
                ]);
            }

            $stockLimit = $product->availableStock();
            $available = ! $product->trashed() && $quantity <= $stockLimit;
            $adjustment = $adjustments[$productId] ?? null;

            if ($product->trashed()) {
                $adjustment ??= 'The product is no longer available.';
            } elseif ($quantity > $stockLimit) {
                $adjustment ??= "Quantity exceeds the current stock limit of {$stockLimit}.";
            }

            $lines[] = [
                'product_id' => $productId,
                'name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $product->trashed() ? '0' : $product->price,
                'tax_rate' => $product->tax_id === null ? '0' : $product->tax->rate,
                'available' => $available,
                'stock_limit' => $stockLimit,
                'adjustment' => $adjustment,
            ];
        }

        if ($shippingMethod !== null && $shippingMethod->currency_id !== $currency->id) {
            throw ValidationException::withMessages([
                'shipping_method_id' => ['The shipping method currency does not match the cart currency.'],
            ]);
        }

        $quote = new CartQuote(
            $lines,
            $currency->code,
            $currency->minor_units,
            $shippingMethod === null ? '0' : $shippingMethod->amount,
            $shippingMethod === null || $shippingMethod->tax_id === null ? '0' : $shippingMethod->tax->rate,
        );

        return response()->json($quote->toArray());
    }

    /** @param Collection<string, Product> $products */
    private function quoteCurrency(Collection $products, ?ShippingMethod $shippingMethod): Currency
    {
        $currencyIds = $products->pluck('currency_id');

        if ($shippingMethod !== null) {
            $currencyIds->push($shippingMethod->currency_id);
        }

        if ($currencyIds->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'lines' => ['All cart products and shipping must use the same currency.'],
            ]);
        }

        $currencyId = $currencyIds->first();

        return $currencyId === null
            ? Currency::query()->where('is_base', true)->firstOrFail()
            : Currency::query()->findOrFail($currencyId);
    }
}
