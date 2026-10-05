<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'payment_status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'email' => ['sometimes', 'string', 'max:254'],
            'number' => ['sometimes', 'string', 'max:64'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:10,20,50,100'],
        ])->validate();
        $query = Order::query()
            ->with(['currency', 'lines', 'address', 'payment', 'manualReview'])
            ->latest('created_at');

        $query->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']));
        $query->when(isset($validated['payment_status']), fn ($query) => $query->whereHas(
            'payment',
            fn ($payment) => $payment->where('status', $validated['payment_status']),
        ));
        $query->when(isset($validated['date_from']), fn ($query) => $query->where('created_at', '>=', $validated['date_from']));
        $query->when(isset($validated['date_to']), fn ($query) => $query->where('created_at', '<=', $validated['date_to']));
        $query->when(isset($validated['email']), fn ($query) => $query->where('normalized_email', mb_strtolower(trim($validated['email']))));
        $query->when(isset($validated['number']), fn ($query) => $query->where('order_code', $validated['number']));
        $page = $query->paginate((int) ($validated['per_page'] ?? 20));

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

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order);
    }
}
