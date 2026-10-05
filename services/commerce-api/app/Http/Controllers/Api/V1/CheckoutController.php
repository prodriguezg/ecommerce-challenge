<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CheckoutConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckoutRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Responses\ProblemDetails;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, CheckoutService $checkoutService): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isCustomer()) {
            return ProblemDetails::response($request, 403, 'Forbidden', 'Administrators cannot place orders.', 'forbidden');
        }

        try {
            $outcome = $checkoutService->checkout($request->validated(), $user instanceof User ? $user : null);
        } catch (CheckoutConflictException $exception) {
            return ProblemDetails::response($request, 409, 'Checkout conflict', $exception->getMessage(), $exception->problemCode);
        }

        if ($outcome->responseStatus !== 202) {
            return ProblemDetails::response(
                $request,
                500,
                'Payment initiation failed',
                'The order was not submitted because payment initiation failed.',
                'payment_initiation_failed',
            );
        }

        $body = [
            'order' => (new OrderResource($outcome->order))->resolve($request),
            'guest' => $outcome->guest,
        ];

        if ($outcome->guestToken !== null) {
            $body['guest_order_url'] = '/api/v1/guest-orders/'.$outcome->order->id.'?guest_token='.$outcome->guestToken;
        }

        return response()->json($body, 202);
    }
}
