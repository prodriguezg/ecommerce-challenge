<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class ProductMutationRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100', 'not_regex:/^\s*$/'],
            'name' => ['required', 'string', 'max:200', 'not_regex:/^\s*$/'],
            'description' => ['required', 'nullable', 'string', 'max:5000'],
            'price' => ['required', 'decimal:2', 'min:0'],
            'currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')->whereNull('deleted_at')],
            'category_id' => ['present', 'nullable', 'ulid', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'tax_id' => ['present', 'nullable', 'ulid', Rule::exists('taxes', 'id')->whereNull('deleted_at')],
            'active' => ['required', 'boolean'],
            'initial_on_hand' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['sku', 'name', 'description', 'price', 'currency', 'category_id', 'tax_id', 'active', 'initial_on_hand'];
    }
}
