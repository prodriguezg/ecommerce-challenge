<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ListProductsRequest extends ApiFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:200'],
            'category' => ['sometimes', 'ulid', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'min_price' => ['sometimes', 'decimal:0,4', 'min:0'],
            'max_price' => ['sometimes', 'decimal:0,4', 'min:0', 'gte:min_price'],
            'in_stock' => ['sometimes', Rule::in(['0', '1', 'false', 'true', 0, 1, false, true])],
            'sort' => ['sometimes', Rule::in(['name', 'price', 'created_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50, 100])],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['q', 'category', 'min_price', 'max_price', 'in_stock', 'sort', 'direction', 'page', 'per_page'];
    }
}
