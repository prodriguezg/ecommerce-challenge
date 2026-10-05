<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CategoryMutationRequest;
use App\Http\Resources\Api\V1\Admin\CategoryResource;
use App\Http\Responses\ProblemDetails;
use App\Models\Category;
use App\Models\Product;
use App\Services\AdminAuditLogger;
use App\Services\OptimisticConcurrency;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function __construct(
        private readonly AdminAuditLogger $audit,
        private readonly OptimisticConcurrency $concurrency,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::withTrashed()->orderBy('name')->get());
    }

    public function store(CategoryMutationRequest $request): JsonResponse
    {
        try {
            $category = DB::transaction(function () use ($request): Category {
                $category = Category::create([
                    'name' => trim($request->string('name')->toString()),
                    'slug' => $request->string('slug')->toString(),
                    'version' => 1,
                ]);
                if (! $request->boolean('active')) {
                    $category->delete();
                }
                $this->audit->record($request, 'category.created', $category, null, $category->fresh()->toArray());

                return $category->fresh();
            });
        } catch (QueryException) {
            return $this->nameConflict($request);
        }

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(string $category): CategoryResource
    {
        return new CategoryResource(Category::withTrashed()->findOrFail($category));
    }

    public function update(CategoryMutationRequest $request, string $category): CategoryResource|JsonResponse
    {
        $expected = $this->concurrency->expectedVersion($request);
        if ($expected instanceof JsonResponse) {
            return $expected;
        }

        try {
            return DB::transaction(function () use ($request, $category, $expected): CategoryResource|JsonResponse {
                $model = Category::withTrashed()->lockForUpdate()->findOrFail($category);
                if (($conflict = $this->concurrency->conflictIfStale($request, $model, $expected)) !== null) {
                    return $conflict;
                }
                $before = $model->toArray();
                $model->fill([
                    'name' => trim($request->string('name')->toString()),
                    'slug' => $request->string('slug')->toString(),
                    'version' => $model->version + 1,
                ])->save();
                if ($request->boolean('active')) {
                    $model->restore();
                } else {
                    Product::withTrashed()->where('category_id', $model->getKey())->update([
                        'category_id' => null,
                        'version' => DB::raw('version + 1'),
                    ]);
                    $model->delete();
                }
                $this->audit->record($request, 'category.updated', $model, $before, $model->fresh()->toArray());

                return new CategoryResource($model->fresh());
            });
        } catch (QueryException) {
            return $this->nameConflict($request);
        }
    }

    public function destroy(Request $request, string $category): Response|JsonResponse
    {
        $expected = $this->concurrency->expectedVersion($request);
        if ($expected instanceof JsonResponse) {
            return $expected;
        }

        return DB::transaction(function () use ($request, $category, $expected): Response|JsonResponse {
            $model = Category::withTrashed()->lockForUpdate()->findOrFail($category);
            if (($conflict = $this->concurrency->conflictIfStale($request, $model, $expected)) !== null) {
                return $conflict;
            }
            $before = $model->toArray();
            Product::withTrashed()->where('category_id', $model->getKey())->update([
                'category_id' => null,
                'version' => DB::raw('version + 1'),
            ]);
            $model->version++;
            $model->save();
            $model->delete();
            $this->audit->record($request, 'category.deleted', $model, $before, $model->fresh()->toArray());

            return response()->noContent();
        });
    }

    private function nameConflict(Request $request): JsonResponse
    {
        return ProblemDetails::response($request, 409, 'Category conflict', 'An active category already uses that name or slug.', 'category_conflict');
    }
}
