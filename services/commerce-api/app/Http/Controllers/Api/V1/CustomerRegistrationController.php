<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterCustomerRequest;
use App\Http\Resources\PrincipalResource;
use App\Http\Responses\ProblemDetails;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerRegistrationController extends Controller
{
    public function create(RegisterCustomerRequest $request): JsonResource|JsonResponse
    {
        try {
            $user = DB::transaction(function () use ($request): User {
                $user = User::query()->create([
                    'role' => UserRole::Customer,
                    'name' => $request->validated('name'),
                    'email' => $request->validated('email'),
                    'password_hash' => $request->validated('password'),
                ]);

                $address = $request->validated('address');
                $user->addresses()->create([
                    'recipient_name' => $address['name'],
                    'line_1' => $address['line1'],
                    'line_2' => $address['line2'] ?? null,
                    'city' => $address['city'],
                    'region' => $address['region'],
                    'postal_code' => $address['postal_code'],
                    'country_code' => $address['country'],
                    'phone' => $address['phone'],
                    'is_default' => true,
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            return ProblemDetails::response(
                $request,
                409,
                'Registration conflict',
                'The account could not be created with the supplied details.',
                'registration_conflict',
            );
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new PrincipalResource($user))->response()->setStatusCode(201);
    }
}
