<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestOrderController extends Controller
{
    public function show(Request $request, string $order): OrderResource
    {
        return new OrderResource($this->accessibleOrder($request, $order));
    }

    public function status(Request $request, string $order): JsonResponse
    {
        return OrderController::statusResponse($this->accessibleOrder($request, $order));
    }

    private function accessibleOrder(Request $request, string $order): Order
    {
        $token = $request->query('guest_token');

        if (! is_string($token) || strlen($token) < 32 || strlen($token) > 255) {
            abort(404);
        }

        return Order::query()
            ->whereNull('customer_user_id')
            ->where('guest_token_hash', hash('sha256', $token))
            ->where('guest_token_expires_at', '>', now())
            ->with(['currency', 'lines', 'address', 'payment'])
            ->findOrFail($order);
    }
}
