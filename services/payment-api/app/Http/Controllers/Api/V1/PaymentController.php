<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AcceptPayment;
use App\Exceptions\IdempotencyConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, AcceptPayment $acceptPayment): JsonResponse
    {
        try {
            $acceptance = $acceptPayment->handle(
                $request->string('idempotency_key')->toString(),
                $request->paymentPayload(),
            );
        } catch (IdempotencyConflictException $exception) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Conflict',
                'status' => 409,
                'detail' => $exception->getMessage(),
                'code' => 'IDEMPOTENCY_CONFLICT',
            ], 409, ['Content-Type' => 'application/problem+json']);
        }

        return response()->json($acceptance, 202);
    }
}
