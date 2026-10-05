<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\InventoryAdjustmentReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ProductImageRequest;
use App\Http\Requests\Api\V1\Admin\ProductMutationRequest;
use App\Http\Resources\Api\V1\Admin\ProductResource;
use App\Http\Responses\ProblemDetails;
use App\Models\Currency;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminAuditLogger;
use App\Services\OptimisticConcurrency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit, private readonly OptimisticConcurrency $concurrency) {}

    public function index(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            'q' => ['sometimes', 'string', 'max:200'],
            'deleted' => ['sometimes', 'in:exclude,include,only'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:10,20,50,100'],
        ])->validate();
        $query = Product::query()->with(['currency', 'category', 'inventory']);
        match ($validated['deleted'] ?? 'exclude') {
            'include' => $query->withTrashed(),
            'only' => $query->onlyTrashed(),
            default => null,
        };
        if (isset($validated['q'])) {
            $search = mb_strtolower(trim($validated['q']));
            $query->where(function (Builder $query) use ($search): void {
                $query->where('normalized_name', 'like', '%'.$search.'%')
                    ->orWhere('normalized_sku', 'like', '%'.mb_strtoupper($search).'%');
            });
        }
        $page = $query->orderBy('name')->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'items' => ProductResource::collection($page->items())->resolve($request),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function store(ProductMutationRequest $request): JsonResponse
    {
        try {
            $product = DB::transaction(function () use ($request): Product {
                $product = Product::create($this->attributes($request) + ['weight_kg' => 0, 'version' => 1]);
                DB::table('product_sku_reservations')->insert([
                    'normalized_sku' => $product->normalized_sku,
                    'product_id' => $product->getKey(),
                    'created_at' => now(),
                ]);
                $inventory = $product->inventory()->create(['stock_on_hand' => $request->integer('initial_on_hand'), 'version' => 1]);
                if ($inventory->stock_on_hand > 0) {
                    $actor = $request->user();
                    InventoryAdjustment::unguarded(fn (): InventoryAdjustment => InventoryAdjustment::create([
                        'inventory_id' => $inventory->getKey(),
                        'product_id' => $product->getKey(),
                        'previous_quantity' => 0,
                        'new_quantity' => $inventory->stock_on_hand,
                        'delta' => $inventory->stock_on_hand,
                        'reason' => InventoryAdjustmentReason::StockReceived,
                        'note' => 'Initial stock on product creation.',
                        'actor_user_id' => $actor instanceof User ? $actor->getKey() : null,
                    ]));
                }
                if (! $request->boolean('active')) {
                    $product->delete();
                }
                $this->audit->record($request, 'product.created', $product, null, $product->fresh()->toArray());

                return $product->fresh(['currency', 'category', 'inventory']);
            });
        } catch (QueryException) {
            return $this->skuConflict($request);
        }

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(string $product): ProductResource
    {
        return new ProductResource(Product::withTrashed()->with(['currency', 'category', 'inventory'])->findOrFail($product));
    }

    public function update(ProductMutationRequest $request, string $product): ProductResource|JsonResponse
    {
        $expected = $this->concurrency->expectedVersion($request);
        if ($expected instanceof JsonResponse) {
            return $expected;
        }

        try {
            return DB::transaction(function () use ($request, $product, $expected): ProductResource|JsonResponse {
                $model = Product::withTrashed()->lockForUpdate()->findOrFail($product);
                if (($conflict = $this->concurrency->conflictIfStale($request, $model, $expected)) !== null) {
                    return $conflict;
                }
                $before = $model->toArray();
                $newSku = Str::upper(Str::squish($request->string('sku')->toString()));
                if ($newSku !== $model->normalized_sku) {
                    DB::table('product_sku_reservations')->insert([
                        'normalized_sku' => $newSku,
                        'product_id' => $model->getKey(),
                        'created_at' => now(),
                    ]);
                }
                $model->fill($this->attributes($request) + ['version' => $model->version + 1])->save();
                if ($request->boolean('active')) {
                    $model->restore();
                } else {
                    $model->cartItems()->delete();
                    $model->delete();
                }
                $this->audit->record($request, 'product.updated', $model, $before, $model->fresh()->toArray());

                return new ProductResource($model->fresh(['currency', 'category', 'inventory']));
            });
        } catch (QueryException) {
            return $this->skuConflict($request);
        }
    }

    public function destroy(Request $request, string $product): Response|JsonResponse
    {
        $expected = $this->concurrency->expectedVersion($request);
        if ($expected instanceof JsonResponse) {
            return $expected;
        }

        $imagePath = DB::transaction(function () use ($request, $product, $expected): string|JsonResponse|null {
            $model = Product::withTrashed()->lockForUpdate()->findOrFail($product);
            if (($conflict = $this->concurrency->conflictIfStale($request, $model, $expected)) !== null) {
                return $conflict;
            }
            $before = $model->toArray();
            $path = $model->image_path;
            $model->cartItems()->delete();
            $hasHistory = $model->orderLines()->exists() || $model->reservationItems()->exists();
            if ($hasHistory) {
                $model->version++;
                $model->save();
                $model->delete();
                $this->audit->record($request, 'product.soft_deleted', $model, $before, $model->fresh()->toArray());
            } else {
                $this->audit->record($request, 'product.hard_deleted', $model, $before, null);
                $model->forceDelete();
            }

            return $path;
        });
        if ($imagePath instanceof JsonResponse) {
            return $imagePath;
        }
        if (is_string($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        return response()->noContent();
    }

    public function replaceImage(ProductImageRequest $request, string $product): ProductResource|JsonResponse
    {
        $file = $request->file('image');
        if ($file === null) {
            return ProblemDetails::validation($request, ['image' => ['An image file is required.']]);
        }
        $detectedMimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $extension = match ($detectedMimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($extension === null) {
            return ProblemDetails::response($request, 415, 'Unsupported media type', 'Only JPEG, PNG, and WebP image content is accepted.', 'unsupported_media_type');
        }
        $newPath = $file->storeAs('products', Str::ulid().'.'.$extension, 'public');
        if (! is_string($newPath)) {
            return ProblemDetails::response($request, 500, 'Image storage failed', 'The image could not be stored.', 'image_storage_failed');
        }

        try {
            [$updated, $oldPath] = DB::transaction(function () use ($request, $product, $newPath, $file, $detectedMimeType): array {
                $model = Product::withTrashed()->lockForUpdate()->findOrFail($product);
                $before = $model->toArray();
                $oldPath = $model->image_path;
                $model->fill([
                    'image_path' => $newPath,
                    'image_mime_type' => $detectedMimeType,
                    'image_size' => $file->getSize(),
                    'version' => $model->version + 1,
                ])->save();
                $this->audit->record($request, 'product.image_replaced', $model, $before, $model->fresh()->toArray());

                return [$model->fresh(['currency', 'category', 'inventory']), $oldPath];
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPath);
            throw $exception;
        }
        if (is_string($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return new ProductResource($updated);
    }

    public function removeImage(Request $request, string $product): ProductResource
    {
        [$updated, $oldPath] = DB::transaction(function () use ($request, $product): array {
            $model = Product::withTrashed()->lockForUpdate()->findOrFail($product);
            $before = $model->toArray();
            $oldPath = $model->image_path;
            $model->fill(['image_path' => null, 'image_mime_type' => null, 'image_size' => null, 'version' => $model->version + 1])->save();
            $this->audit->record($request, 'product.image_removed', $model, $before, $model->fresh()->toArray());

            return [$model->fresh(['currency', 'category', 'inventory']), $oldPath];
        });
        if (is_string($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return new ProductResource($updated);
    }

    /** @return array<string, mixed> */
    private function attributes(ProductMutationRequest $request): array
    {
        return [
            'sku' => trim($request->string('sku')->toString()),
            'name' => trim($request->string('name')->toString()),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'currency_id' => Currency::where('code', $request->string('currency')->upper()->toString())->value('id'),
            'category_id' => $request->input('category_id'),
            'tax_id' => $request->input('tax_id'),
        ];
    }

    private function skuConflict(Request $request): JsonResponse
    {
        return ProblemDetails::response($request, 409, 'SKU conflict', 'That SKU is permanently reserved or another catalog constraint conflicts.', 'product_conflict');
    }
}
