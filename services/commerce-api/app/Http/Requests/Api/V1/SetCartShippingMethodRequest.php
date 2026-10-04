<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class SetCartShippingMethodRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shipping_method_id' => [
                'present',
                'nullable',
                'ulid',
                Rule::exists('shipping_methods', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['shipping_method_id'];
    }
}
