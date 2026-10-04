<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), $this->permittedKeys()) as $key) {
                $validator->errors()->add($key, 'The field is not allowed.');
            }
        });
    }

    /** @return list<string> */
    abstract protected function permittedKeys(): array;
}
