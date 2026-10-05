<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\ApiFormRequest;

class SettingMutationRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'value' => ['required', 'integer', 'min:30', 'max:3600'],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['value'];
    }
}
