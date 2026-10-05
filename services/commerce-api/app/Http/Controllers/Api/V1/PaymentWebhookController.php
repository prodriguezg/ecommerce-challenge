<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PaymentWebhookConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PaymentWebhookRequest;
use App\Http\Responses\ProblemDetails;
use App\Services\PaymentTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PaymentWebhookController extends Controller
{
    public function __invoke(PaymentWebhookRequest $request, PaymentTransitionService $transitions): Response|JsonResponse
    {
        try {
            /** @var array{event_id: string, commerce_payment_id: string, provider_payment_id: string, outcome: string, provider_code: string, occurred_at: string} $payload */
            $payload = $request->validated();
            $transitions->process($payload);
        } catch (PaymentWebhookConflictException $exception) {
            return ProblemDetails::response(
                $request,
                409,
                'Payment event conflict',
                $exception->getMessage(),
                'payment_event_conflict',
            );
        }

        return response()->noContent();
    }
}
