<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\GuestOrderClaimConflictException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class GuestOrderRegistrationService
{
    public function claim(string $orderId, string $guestToken, string $password): User
    {
        try {
            return DB::transaction(function () use ($orderId, $guestToken, $password): User {
                $order = Order::query()
                    ->whereNull('customer_user_id')
                    ->where('guest_token_hash', hash('sha256', $guestToken))
                    ->where('guest_token_expires_at', '>', now())
                    ->with('address')
                    ->lockForUpdate()
                    ->find($orderId);

                if (! $order instanceof Order) {
                    throw (new ModelNotFoundException)->setModel(Order::class, [$orderId]);
                }

                if ($order->status !== OrderStatus::Paid) {
                    throw new GuestOrderClaimConflictException(
                        'order_not_eligible',
                        'An account can be created from this order only after a successful purchase.',
                    );
                }

                if (User::query()->where('normalized_email', $order->normalized_email)->exists()) {
                    throw new GuestOrderClaimConflictException(
                        'login_required',
                        'Sign in to use the account associated with this order email address.',
                    );
                }

                $user = User::query()->create([
                    'role' => UserRole::Customer,
                    'name' => $order->address->recipient_name,
                    'email' => $order->email,
                    'password_hash' => $password,
                ]);
                $user->addresses()->create([
                    'recipient_name' => $order->address->recipient_name,
                    'line_1' => $order->address->line_1,
                    'line_2' => $order->address->line_2,
                    'city' => $order->address->city,
                    'region' => $order->address->region,
                    'postal_code' => $order->address->postal_code,
                    'country_code' => $order->address->country_code,
                    'phone' => $order->address->phone,
                    'is_default' => true,
                ]);
                $order->forceFill([
                    'customer_user_id' => $user->id,
                    'guest_token_hash' => null,
                    'guest_token_expires_at' => null,
                    'version' => $order->version + 1,
                ])->save();

                return $user;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new GuestOrderClaimConflictException(
                'login_required',
                'Sign in to use the account associated with this order email address.',
            );
        }
    }
}
