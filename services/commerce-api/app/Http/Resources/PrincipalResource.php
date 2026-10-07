<?php

namespace App\Http\Resources;

use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class PrincipalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $principal */
        $principal = [
            'id' => (string) $this->resource->getKey(),
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $this->resource->roleValue(),
        ];

        if (! $this->resource->isCustomer()) {
            return $principal;
        }

        $defaultAddress = $this->resource->addresses()
            ->where('is_default', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if (! $defaultAddress instanceof Address) {
            return $principal;
        }

        $address = [
            'name' => $defaultAddress->recipient_name,
            'line1' => $defaultAddress->line_1,
            'city' => $defaultAddress->city,
            'region' => $defaultAddress->region,
            'postal_code' => $defaultAddress->postal_code,
            'country' => $defaultAddress->country_code,
            'phone' => $defaultAddress->phone,
        ];

        if ($defaultAddress->line_2 !== null) {
            $address['line2'] = $defaultAddress->line_2;
        }

        $principal['default_address'] = $address;

        return $principal;
    }
}
