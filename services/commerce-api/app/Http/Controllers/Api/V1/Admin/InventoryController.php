<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\InventoryAdjustmentReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\InventoryAdjustmentRequest;
use App\Http\Resources\Api\V1\Admin\InventoryAdjustmentResource;
use App\Http\Resources\Api\V1\Admin\InventoryResource;
use App\Http\Responses\ProblemDetails;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function show(string $product): InventoryResource
    {
        Product::withTrashed()->findOrFail($product);
        $inventory = Inventory::where('product_id', $product)->firstOrFail();

        return new InventoryResource($inventory);
    }

    public function index(Request $request, string $product): JsonResponse
    {
        Product::withTrashed()->findOrFail($product);
        $validated = validator($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:10,20,50,100'],
        ])->validate();
        $page = InventoryAdjustment::where('product_id', $product)
            ->latest('created_at')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'items' => InventoryAdjustmentResource::collection($page->items())->resolve($request),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function store(InventoryAdjustmentRequest $request, string $product): JsonResponse
    {
        return DB::transaction(function () use ($request, $product): JsonResponse {
            Product::withTrashed()->findOrFail($product);
            $inventory = Inventory::where('product_id', $product)->lockForUpdate()->firstOrFail();
            $reserved = $this->reservedQuantity($inventory);
            $newQuantity = $request->integer('on_hand');
            if ($newQuantity < $reserved) {
                return ProblemDetails::response(
                    $request,
                    409,
                    'Reserved inventory conflict',
                    'Stock on hand cannot be reduced below the active reserved quantity.',
                    'inventory_reservation_floor',
                );
            }
            $before = $inventory->toArray();
            $previous = (int) $inventory->stock_on_hand;
            $inventory->fill(['stock_on_hand' => $newQuantity, 'version' => $inventory->version + 1])->save();
            $actor = $request->user();
            InventoryAdjustment::unguarded(fn (): InventoryAdjustment => InventoryAdjustment::create([
                'inventory_id' => $inventory->getKey(),
                'product_id' => $product,
                'previous_quantity' => $previous,
                'new_quantity' => $newQuantity,
                'delta' => $newQuantity - $previous,
                'reason' => InventoryAdjustmentReason::from($request->string('reason')->toString()),
                'note' => $request->input('note'),
                'actor_user_id' => $actor instanceof User ? $actor->getKey() : null,
            ]));
            $this->audit->record($request, 'inventory.adjusted', $inventory, $before, $inventory->fresh()->toArray());

            return (new InventoryResource($inventory))->response()->setStatusCode(201);
        });
    }

    private function reservedQuantity(Inventory $inventory): int
    {
        return $inventory->reservedQuantity();
    }
}
