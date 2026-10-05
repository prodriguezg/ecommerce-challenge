<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\InventoryAdjustmentReason;
use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class InventoryAdjustmentRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'on_hand' => ['required', 'integer', 'min:0'],
            'reason' => ['required', Rule::enum(InventoryAdjustmentReason::class)->except(InventoryAdjustmentReason::CsvImport)],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:reason,other'],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['on_hand', 'reason', 'note'];
    }
}
