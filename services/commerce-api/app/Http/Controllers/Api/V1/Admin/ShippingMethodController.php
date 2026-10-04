<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ShippingMethodMutationRequest;
use App\Http\Resources\Api\V1\Admin\ShippingMethodResource;
use App\Http\Responses\ProblemDetails;
use App\Models\Cart;
use App\Models\Currency;
use App\Models\ShippingMethod;
use App\Services\AdminAuditLogger;
use App\Services\OptimisticConcurrency;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ShippingMethodController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit, private readonly OptimisticConcurrency $concurrency) {}

    public function index(): AnonymousResourceCollection
    {
        return ShippingMethodResource::collection(ShippingMethod::withTrashed()->with('currency')->orderBy('name')->get());
    }

    public function store(ShippingMethodMutationRequest $request): JsonResponse
    {
        try {
            $method = DB::transaction(function () use ($request): ShippingMethod {
                $method = ShippingMethod::create($this->attributes($request) + ['version' => 1]);
                if (! $request->boolean('active')) {
                    $method->delete();
                }
                $this->audit->record($request, 'shipping_method.created', $method, null, $method->fresh()->toArray());

                return $method->fresh(['currency']);
            });
        } catch (QueryException) {
            return $this->nameConflict($request);
        }

        return (new ShippingMethodResource($method))->response()->setStatusCode(201);
    }

    public function show(string $shippingMethod): ShippingMethodResource
    {
        return new ShippingMethodResource(ShippingMethod::withTrashed()->with('currency')->findOrFail($shippingMethod));
    }

    public function update(ShippingMethodMutationRequest $request, string $shippingMethod): ShippingMethodResource|JsonResponse
    {
        $expected = $this->concurrency->expectedVersion($request);
        if ($expected instanceof JsonResponse) {
            return $expected;
        }

        try {
            return DB::transaction(function () use ($request, $shippingMethod, $expected): ShippingMethodResource|JsonResponse {
                $model = ShippingMethod::withTrashed()->lockForUpdate()->findOrFail($shippingMethod);
                if (($conflict = $this->concurrency->conflictIfStale($request, $model, $expected)) !== null) {
                    return $conflict;
                }
                $before = $model->toArray();
                $model->fill($this->attributes($request) + ['version' => $model->version + 1])->save();
                if ($request->boolean('active')) {
                    $model->restore();
                } else {
                    Cart::where('shipping_method_id', $model->getKey())->update(['shipping_method_id' => null, 'version' => DB::raw('version + 1')]);
                    $model->delete();
                }
                $this->audit->record($request, 'shipping_method.updated', $model, $before, $model->fresh()->toArray());

                return new ShippingMethodResource($model->fresh(['currency']));
            });
        } catch (QueryException) {
            return $this->nameConflict($request);
        }
    }

    public function destroy(Request $request, string $shippingMethod): Response|JsonResponse
    {
        $expected = $this->concurrency->expectedVersion($request);
        if ($expected instanceof JsonResponse) {
            return $expected;
        }

        return DB::transaction(function () use ($request, $shippingMethod, $expected): Response|JsonResponse {
            $model = ShippingMethod::withTrashed()->lockForUpdate()->findOrFail($shippingMethod);
            if (($conflict = $this->concurrency->conflictIfStale($request, $model, $expected)) !== null) {
                return $conflict;
            }
            $before = $model->toArray();
            Cart::where('shipping_method_id', $model->getKey())->update(['shipping_method_id' => null, 'version' => DB::raw('version + 1')]);
            $model->version++;
            $model->save();
            $model->delete();
            $this->audit->record($request, 'shipping_method.deleted', $model, $before, $model->fresh()->toArray());

            return response()->noContent();
        });
    }

    /** @return array<string, mixed> */
    private function attributes(ShippingMethodMutationRequest $request): array
    {
        return [
            'name' => trim($request->string('name')->toString()),
            'amount' => $request->input('amount'),
            'currency_id' => Currency::where('code', $request->string('currency')->upper()->toString())->value('id'),
            'tax_id' => $request->input('tax_id'),
        ];
    }

    private function nameConflict(Request $request): JsonResponse
    {
        return ProblemDetails::response($request, 409, 'Shipping method conflict', 'An active shipping method already uses that name.', 'shipping_method_conflict');
    }
}
