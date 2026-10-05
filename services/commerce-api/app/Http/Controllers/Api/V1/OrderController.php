<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50, 100])],
        ]);
        $customer = $this->customer($request);
        $orders = Order::query()
            ->where('customer_user_id', $customer->id)
            ->with(['currency', 'lines', 'address', 'payment'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'items' => OrderResource::collection($orders->items())->resolve($request),
            'pagination' => [
                'page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'total_pages' => $orders->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $order): OrderResource
    {
        return new OrderResource($this->ownedOrder($request, $order));
    }

    public function status(Request $request, string $order): JsonResponse
    {
        return $this->statusResponse($this->ownedOrder($request, $order));
    }

    public static function statusResponse(Order $order): JsonResponse
    {
        $order->loadMissing('payment');
        $resource = new OrderResource($order);

        return response()->json([
            'order_id' => $order->id,
            'status' => OrderStatus::from((string) $order->getRawOriginal('status'))->value,
            'payment_status' => $resource->paymentStatus(),
            'updated_at' => $order->updated_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ]);
    }

    private function ownedOrder(Request $request, string $order): Order
    {
        return Order::query()
            ->where('customer_user_id', $this->customer($request)->id)
            ->with(['currency', 'lines', 'address', 'payment'])
            ->findOrFail($order);
    }

    private function customer(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
