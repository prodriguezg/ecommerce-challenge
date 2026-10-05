<?php

namespace App\Http\Requests;

use App\Http\Responses\ProblemDetails;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:255'],
            'commerce_payment_id' => ['required', 'string', 'ulid'],
            'commerce_order_id' => ['required', 'string', 'ulid'],
            'amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]*)\.[0-9]{2}$/'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'callback_url' => ['required', 'url:http,https', Rule::in([(string) config('payment.callback_url')])],
            'callback_reference' => ['required', 'string', 'max:255'],
            'payment_test_number' => ['required', 'string', Rule::in([
                '4000000000010001',
                '4000000000000002',
                '4000000000090003',
                '4000000000080004',
            ])],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = [
                'commerce_payment_id',
                'commerce_order_id',
                'amount',
                'currency',
                'callback_url',
                'callback_reference',
                'payment_test_number',
                'idempotency_key',
            ];
            $unexpected = array_diff(array_keys($this->json()->all()), $allowed);

            if ($unexpected !== []) {
                $validator->errors()->add('request', 'The request contains unsupported fields.');
            }
        }];
    }

    /** @return array<string, string> */
    public function paymentPayload(): array
    {
        /** @var array<string, string> $payload */
        $payload = $this->safe()->except('idempotency_key');

        return $payload;
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(ProblemDetails::response(
            $this,
            422,
            'Validation failed',
            'The payment request was invalid.',
            'validation_error',
            $validator->errors()->toArray(),
        ));
    }
}
