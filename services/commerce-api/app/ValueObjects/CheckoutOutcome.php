<?php

namespace App\ValueObjects;

use App\Models\Order;

final readonly class CheckoutOutcome
{
    public function __construct(
        public Order $order,
        public bool $guest,
        public ?string $guestToken,
        public int $responseStatus,
    ) {}
}
