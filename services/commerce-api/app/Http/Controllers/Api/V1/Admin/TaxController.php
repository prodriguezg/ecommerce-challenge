<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\TaxMutationRequest;
use App\Http\Resources\Api\V1\Admin\TaxResource;
use App\Http\Responses\ProblemDetails;
use App\Models\Tax;
use App\Services\AdminAuditLogger;
use App\Services\OptimisticConcurrency;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TaxController extends Controller
{
    public function __construct(private readonly AdminAuditLogger $audit, private readonly OptimisticConcurrency $concurrency) {}

    public function index(): AnonymousResourceCollection
    {
        return TaxResource::collection(Tax::withTrashed()->orderBy('name')->get());
    }

    public function store(TaxMutationRequest $request): JsonResponse
    {
        try {
            $tax = DB::transaction(function () use ($request): Tax {
                $tax = Tax::create(['name' => trim($request->string('name')->toString()), 'rate' => $request->input('rate'), 'version' => 1]);
                if (! $request->boolean('active')) {
                    $tax->delete();
                }
                $this->audit->record($request, 'tax.created', $tax, null, $tax->fresh()->toArray());

                return $tax->fresh();
            });
        } catch (QueryException) {
            return $this->nameConflict($request);
        }

        return (new TaxResource($tax))->response()->setStatusCode(201);
    }

    public function show(string $tax): TaxResource
    {
        return new TaxResource(Tax::withTrashed()->findOrFail($tax));
    }

    public function update(TaxMutationRequest $request, string $tax): TaxResource|JsonResponse
    {
        $expected = $this->concurrency->expectedVersion($request);
        if ($expected instanceof JsonResponse) {
            return $expected;
        }

        try {
            return DB::transaction(function () use ($request, $tax, $expected): TaxResource|JsonResponse {
                $model = Tax::withTrashed()->lockForUpdate()->findOrFail($tax);
                if (($conflict = $this->concurrency->conflictIfStale($request, $model, $expected)) !== null) {
                    return $conflict;
                }
                if (! $request->boolean('active')) {
                    $referenceConflict = $this->referenceConflict($request, $model);
                    if ($referenceConflict !== null) {
                        return $referenceConflict;
                    }
                }
                $before = $model->toArray();
                $model->fill(['name' => trim($request->string('name')->toString()), 'rate' => $request->input('rate'), 'version' => $model->version + 1])->save();
                $request->boolean('active') ? $model->restore() : $model->delete();
                $this->audit->record($request, 'tax.updated', $model, $before, $model->fresh()->toArray());

                return new TaxResource($model->fresh());
            });
        } catch (QueryException) {
            return $this->nameConflict($request);
        }
    }

    public function destroy(Request $request, string $tax): Response|JsonResponse
    {
        $expected = $this->concurrency->expectedVersion($request);
        if ($expected instanceof JsonResponse) {
            return $expected;
        }

        return DB::transaction(function () use ($request, $tax, $expected): Response|JsonResponse {
            $model = Tax::withTrashed()->lockForUpdate()->findOrFail($tax);
            if (($conflict = $this->concurrency->conflictIfStale($request, $model, $expected)) !== null) {
                return $conflict;
            }
            $referenceConflict = $this->referenceConflict($request, $model);
            if ($referenceConflict !== null) {
                return $referenceConflict;
            }
            $before = $model->toArray();
            $model->version++;
            $model->save();
            $model->delete();
            $this->audit->record($request, 'tax.deleted', $model, $before, $model->fresh()->toArray());

            return response()->noContent();
        });
    }

    private function nameConflict(Request $request): JsonResponse
    {
        return ProblemDetails::response($request, 409, 'Tax conflict', 'An active tax already uses that name.', 'tax_conflict');
    }

    private function referenceConflict(Request $request, Tax $tax): ?JsonResponse
    {
        $productCount = $tax->products()->whereNull('products.deleted_at')->count();
        $shippingCount = $tax->shippingMethods()->whereNull('shipping_methods.deleted_at')->count();
        if ($productCount + $shippingCount === 0) {
            return null;
        }

        return ProblemDetails::response(
            $request, 409, 'Tax is in use', 'Active catalog records still reference this tax.', 'tax_in_use',
            extensions: ['active_product_references' => $productCount, 'active_shipping_method_references' => $shippingCount],
        );
    }
}
