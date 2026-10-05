<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ImportMode;
use App\Enums\UnknownCategoryPolicy;
use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class ProductImportRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'mode' => ['required', Rule::enum(ImportMode::class)],
            'unknown_category_policy' => ['required', Rule::enum(UnknownCategoryPolicy::class)],
            'override_stock' => ['required', 'boolean'],
            'confirm_stock_override' => ['required_if:override_stock,true,1', 'boolean', 'accepted_if:override_stock,true,1'],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['file', 'mode', 'unknown_category_policy', 'override_stock', 'confirm_stock_override'];
    }
}
