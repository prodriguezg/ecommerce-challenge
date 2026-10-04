<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class QuoteCartRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'max:100'],
            'lines.*' => ['array:product_id,quantity'],
            'lines.*.product_id' => ['required', 'ulid'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'shipping_method_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('shipping_methods', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['lines', 'shipping_method_id'];
    }
}
