<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

class RegisterGuestOrderRequest extends ApiFormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255', Password::min(10)->numbers()->symbols(), 'regex:/[A-Z]/'],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['password', 'guest_token'];
    }
}
