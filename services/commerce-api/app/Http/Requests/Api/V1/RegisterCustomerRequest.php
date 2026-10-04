<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCustomerRequest extends ApiFormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:200'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique('users', 'normalized_email')],
            'password' => ['required', 'string', 'max:255', Password::min(10)->numbers()->symbols(), 'regex:/[A-Z]/'],
            'address' => ['required', 'array:name,line1,line2,city,region,postal_code,country,phone'],
            'address.name' => ['required', 'string', 'min:1', 'max:200'],
            'address.line1' => ['required', 'string', 'min:1', 'max:200'],
            'address.line2' => ['nullable', 'string', 'max:200'],
            'address.city' => ['required', 'string', 'min:1', 'max:100'],
            'address.region' => ['required', 'string', 'min:1', 'max:100'],
            'address.postal_code' => ['required', 'string', 'min:1', 'max:32'],
            'address.country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'address.phone' => ['required', 'string', 'min:1', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('address');

        if (is_array($address) && is_string($address['country'] ?? null)) {
            $address['country'] = strtoupper(trim($address['country']));
        }

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'address' => $address,
        ]);
    }

    protected function permittedKeys(): array
    {
        return ['name', 'email', 'password', 'address'];
    }
}
