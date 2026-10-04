<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;

class MergeCartRequest extends ApiFormRequest
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
        ];
    }

    protected function permittedKeys(): array
    {
        return ['lines'];
    }
}
