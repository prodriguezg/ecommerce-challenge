<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class CheckoutRequest extends ApiFormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'shipping_address' => ['required', 'array:name,line1,line2,city,region,postal_code,country,phone'],
            'shipping_address.name' => ['required', 'string', 'min:1', 'max:200'],
            'shipping_address.line1' => ['required', 'string', 'min:1', 'max:200'],
            'shipping_address.line2' => ['nullable', 'string', 'max:200'],
            'shipping_address.city' => ['required', 'string', 'min:1', 'max:100'],
            'shipping_address.region' => ['required', 'string', 'min:1', 'max:100'],
            'shipping_address.postal_code' => ['required', 'string', 'min:1', 'max:32'],
            'shipping_address.country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'shipping_address.phone' => ['required', 'string', 'min:1', 'max:32'],
            'shipping_method_id' => ['required', 'ulid', Rule::exists('shipping_methods', 'id')->whereNull('deleted_at')],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*' => ['array:product_id,quantity'],
            'lines.*.product_id' => ['required', 'ulid'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'payment_test_number' => ['required', 'string', Rule::in([
                '4000000000010001',
                '4000000000000002',
                '4000000000090003',
                '4000000000080004',
            ])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('shipping_address');

        if (is_array($address) && is_string($address['country'] ?? null)) {
            $address['country'] = strtoupper(trim($address['country']));
        }

        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'shipping_address' => $address,
        ]);
    }

    protected function permittedKeys(): array
    {
        return ['email', 'shipping_address', 'shipping_method_id', 'lines', 'payment_test_number', 'idempotency_key'];
    }
}
