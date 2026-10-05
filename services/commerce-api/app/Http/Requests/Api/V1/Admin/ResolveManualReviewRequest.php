<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\ApiFormRequest;

class ResolveManualReviewRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:1', 'max:2000', 'regex:/\\S/'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['note', 'version'];
    }
}
