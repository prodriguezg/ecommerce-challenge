<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\ApiFormRequest;

class ProductImageRequest extends ApiFormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['image' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp']];
    }

    protected function permittedKeys(): array
    {
        return ['image'];
    }
}
