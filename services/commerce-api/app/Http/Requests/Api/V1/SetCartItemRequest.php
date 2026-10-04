<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;

class SetCartItemRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['quantity' => ['required', 'integer', 'min:1', 'max:999']];
    }

    protected function permittedKeys(): array
    {
        return ['quantity'];
    }
}
