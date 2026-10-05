<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AcceptPayment;
use App\Exceptions\IdempotencyConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Responses\ProblemDetails;
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
            return ProblemDetails::response(
                $request,
                409,
                'Conflict',
                $exception->getMessage(),
                'idempotency_conflict',
            );
        }

        return response()->json($acceptance, 202);
    }
}
