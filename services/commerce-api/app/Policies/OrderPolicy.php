<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewCustomerOrder(User $user, Order $order): bool
    {
        return $user->isCustomer() && $order->customer_user_id === $user->id;
    }

    public function viewAnyAsAdministrator(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function viewAsAdministrator(User $user, Order $order): bool
    {
        return $user->isAdministrator();
    }
}
