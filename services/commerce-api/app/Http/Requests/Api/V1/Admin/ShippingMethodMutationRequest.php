<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class ShippingMethodMutationRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200', 'not_regex:/^\s*$/'],
            'amount' => ['required', 'decimal:2', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')->whereNull('deleted_at')],
            'tax_id' => ['present', 'nullable', 'ulid', Rule::exists('taxes', 'id')->whereNull('deleted_at')],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['name', 'amount', 'currency', 'tax_id', 'active'];
    }
}
