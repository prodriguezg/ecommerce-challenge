<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\GuestOrderClaimConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterGuestOrderRequest;
use App\Http\Resources\PrincipalResource;
use App\Http\Responses\ProblemDetails;
use App\Services\GuestOrderRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class GuestOrderRegistrationController extends Controller
{
    public function store(
        RegisterGuestOrderRequest $request,
        string $order,
        GuestOrderRegistrationService $registrationService,
    ): JsonResource|JsonResponse {
        $guestToken = $request->query('guest_token');

        if (! is_string($guestToken) || strlen($guestToken) < 32 || strlen($guestToken) > 255) {
            abort(404);
        }

        try {
            $user = $registrationService->claim($order, $guestToken, $request->validated('password'));
        } catch (GuestOrderClaimConflictException $exception) {
            return ProblemDetails::response(
                $request,
                409,
                'Registration conflict',
                $exception->getMessage(),
                $exception->problemCode,
            );
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new PrincipalResource($user))->response()->setStatusCode(201);
    }
}
