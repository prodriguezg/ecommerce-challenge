<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListProductsRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ShippingMethodResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(ListProductsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = Product::query()
            ->with(['category', 'currency', 'inventory'])
            ->withSum('activeReservationItems as reserved_quantity', 'quantity');

        if (isset($validated['q']) && trim((string) $validated['q']) !== '') {
            $search = '%'.mb_strtolower(trim((string) $validated['q'])).'%';
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->whereLike('normalized_name', $search)
                    ->orWhereLike('normalized_sku', $search)
                    ->orWhereLike('description', $search)
                    ->orWhereHas('category', fn (Builder $category) => $category->whereLike('normalized_name', $search));
            });
        }

        if (isset($validated['category'])) {
            $query->where('category_id', $validated['category']);
        }

        if (isset($validated['min_price'])) {
            $query->where('price', '>=', $validated['min_price']);
        }

        if (isset($validated['max_price'])) {
            $query->where('price', '<=', $validated['max_price']);
        }

        if (array_key_exists('in_stock', $validated)) {
            $this->applyStockFilter($query, $request->boolean('in_stock'));
        }

        $sortColumn = match ($validated['sort'] ?? 'name') {
            'price' => 'price',
            'created_at' => 'created_at',
            default => 'normalized_name',
        };
        $direction = $validated['direction'] ?? 'asc';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $products = $query
            ->orderBy($sortColumn, $direction)
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'items' => ProductResource::collection($products->getCollection())->resolve($request),
            'pagination' => [
                'page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'total_pages' => $products->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $product->load(['category', 'currency', 'inventory'])
            ->loadSum('activeReservationItems as reserved_quantity', 'quantity');

        return new ProductResource($product);
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = Category::query()->orderBy('normalized_name')->get();

        return response()->json(CategoryResource::collection($categories)->resolve($request));
    }

    public function shippingMethods(Request $request): JsonResponse
    {
        $shippingMethods = ShippingMethod::query()->with('currency')->orderBy('normalized_name')->get();

        return response()->json(ShippingMethodResource::collection($shippingMethods)->resolve($request));
    }

    /** @param Builder<Product> $query */
    private function applyStockFilter(Builder $query, bool $inStock): void
    {
        $comparison = $inStock ? '>' : '<=';
        $query->whereRaw(
            "COALESCE((SELECT stock_on_hand FROM inventories WHERE inventories.product_id = products.id), 0) {$comparison} "
            .'COALESCE((SELECT SUM(reservation_items.quantity) FROM reservation_items '
            .'INNER JOIN reservations ON reservations.id = reservation_items.reservation_id '
            .'WHERE reservation_items.product_id = products.id AND reservations.status = ? AND reservations.expires_at > ?), 0)',
            ['active', now()],
        );
    }
}
