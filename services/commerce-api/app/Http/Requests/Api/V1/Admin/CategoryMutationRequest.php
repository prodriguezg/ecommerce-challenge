<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\ApiFormRequest;

class CategoryMutationRequest extends ApiFormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200', 'not_regex:/^\s*$/'],
            'slug' => ['required', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'active' => ['required', 'boolean'],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['name', 'slug', 'active'];
    }
}
