<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SetupAdminRequest;
use App\Http\Resources\PrincipalResource;
use App\Http\Responses\ProblemDetails;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SetupController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'available' => ! User::query()->where('role', UserRole::Admin)->exists(),
        ]);
    }

    public function create(SetupAdminRequest $request): JsonResource|JsonResponse
    {
        try {
            $user = DB::transaction(function () use ($request): ?User {
                if (User::query()->where('role', UserRole::Admin)->exists()) {
                    return null;
                }

                return User::query()->create([
                    'role' => UserRole::Admin,
                    'name' => $request->validated('name'),
                    'email' => $request->validated('email'),
                    'password_hash' => $request->validated('password'),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $user = null;
        }

        if ($user === null) {
            return ProblemDetails::response(
                $request,
                409,
                'Setup unavailable',
                'Administrator setup is no longer available.',
                'setup_unavailable',
            );
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new PrincipalResource($user))->response()->setStatusCode(201);
    }
}
