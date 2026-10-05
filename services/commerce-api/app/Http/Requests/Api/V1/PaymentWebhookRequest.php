<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class PaymentWebhookRequest extends ApiFormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'ulid'],
            'commerce_payment_id' => ['required', 'string', 'ulid'],
            'provider_payment_id' => ['required', 'string', 'ulid'],
            'outcome' => ['required', 'string', Rule::in(['succeeded', 'declined', 'error'])],
            'provider_code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9_]+$/'],
            'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i:s\\Z'],
        ];
    }

    protected function permittedKeys(): array
    {
        return ['event_id', 'commerce_payment_id', 'provider_payment_id', 'outcome', 'provider_code', 'occurred_at'];
    }
}
