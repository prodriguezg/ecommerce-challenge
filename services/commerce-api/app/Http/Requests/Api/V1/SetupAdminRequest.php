<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SetupAdminRequest extends ApiFormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:200'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique('users', 'normalized_email')],
            'password' => ['required', 'string', 'max:255', Password::min(10)->numbers()->symbols(), 'regex:/[A-Z]/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    protected function permittedKeys(): array
    {
        return ['name', 'email', 'password'];
    }
}
