<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\PrincipalResource;
use App\Http\Responses\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticationController extends Controller
{
    public function login(LoginRequest $request): JsonResource|JsonResponse
    {
        $authenticated = Auth::guard('web')->attempt([
            'normalized_email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        if (! $authenticated) {
            return ProblemDetails::response(
                $request,
                401,
                'Unauthenticated',
                'The provided credentials are invalid.',
                'invalid_credentials',
            );
        }

        $request->session()->regenerate();

        return new PrincipalResource($request->user('web'));
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): PrincipalResource
    {
        return new PrincipalResource($request->user());
    }
}
