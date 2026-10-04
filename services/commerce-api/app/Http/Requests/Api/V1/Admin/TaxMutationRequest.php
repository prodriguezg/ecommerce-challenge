<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\ApiFormRequest;

class TaxMutationRequest extends ApiFormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200', 'not_regex:/^\s*$/'],
            'rate' => ['required', 'decimal:0,4', 'min:0', 'max:100'],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['name', 'rate', 'active'];
    }
}
