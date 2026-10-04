<?php

namespace Tests\Feature\Authorization;

use App\Http\Middleware\EnsureUserHasRole;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Policies\CartPolicy;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_customer_can_access_only_own_cart_and_customer_order(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $cart = Cart::factory()->for($customer)->create();
        $order = Order::factory()->for($customer, 'customer')->create();

        $this->assertTrue((new CartPolicy)->view($customer, $cart));
        $this->assertFalse((new CartPolicy)->view($otherCustomer, $cart));
        $this->assertTrue((new OrderPolicy)->viewCustomerOrder($customer, $order));
        $this->assertFalse((new OrderPolicy)->viewCustomerOrder($otherCustomer, $order));
    }

    public function test_administrator_cannot_access_customer_cart_or_customer_order_capability(): void
    {
        $administrator = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $cart = Cart::factory()->for($customer)->create();
        $order = Order::factory()->for($customer, 'customer')->create();

        $this->assertFalse((new CartPolicy)->view($administrator, $cart));
        $this->assertFalse((new OrderPolicy)->viewCustomerOrder($administrator, $order));
        $this->assertTrue((new OrderPolicy)->viewAsAdministrator($administrator, $order));
        $this->assertTrue((new OrderPolicy)->viewAnyAsAdministrator($administrator));
    }

    public function test_role_middleware_returns_403_problem_for_the_wrong_authenticated_role(): void
    {
        $administrator = User::factory()->admin()->create();
        $request = Request::create('/api/v1/cart', 'GET');
        $request->setUserResolver(fn (): User => $administrator);

        $response = (new EnsureUserHasRole)->handle(
            $request,
            fn () => response()->noContent(),
            'customer',
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('content-type'));
        $this->assertSame('forbidden', $response->getData(true)['code']);
    }
}
