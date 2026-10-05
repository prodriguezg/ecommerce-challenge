<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ManualReviewStatus;
use App\Exceptions\ManualReviewConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ResolveManualReviewRequest;
use App\Http\Resources\Api\V1\Admin\OrderResource;
use App\Http\Responses\ProblemDetails;
use App\Models\ManualReview;
use App\Models\Order;
use App\Services\ManualReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManualReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:10,20,50,100'],
        ])->validate();
        $page = Order::query()
            ->whereHas('manualReview', fn ($query) => $query->where('status', ManualReviewStatus::Pending->value))
            ->with(['currency', 'lines', 'address', 'payment', 'manualReview'])
            ->latest('created_at')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'items' => OrderResource::collection($page->items())->resolve($request),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function resolve(
        ResolveManualReviewRequest $request,
        ManualReview $review,
        ManualReviewService $reviews,
    ): OrderResource|JsonResponse {
        try {
            $order = $reviews->resolve(
                $request,
                $review,
                $request->string('note')->trim()->toString(),
                $request->integer('version'),
            );
        } catch (ManualReviewConflictException $exception) {
            return ProblemDetails::response(
                $request,
                409,
                'Manual review conflict',
                $exception->getMessage(),
                'manual_review_conflict',
            );
        }

        return new OrderResource($order);
    }
}
