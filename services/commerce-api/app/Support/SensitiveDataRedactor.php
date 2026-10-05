<?php

namespace App\Support;

final class SensitiveDataRedactor
{
    private const REDACTED = '[REDACTED]';

    /**
     * @param  array<string|int, mixed>  $values
     * @return array<string|int, mixed>
     */
    public function redactArray(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = match (true) {
                is_array($value) => $this->redactArray($value),
                is_string($value) => $this->redactText($value),
                default => $value,
            };
        }

        return $redacted;
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @return array<string|int, mixed>
     */
    public function removeSensitiveKeys(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->removeSensitiveKeys($value) : $value;
        }

        return $redacted;
    }

    public function redactText(string $value): string
    {
        $value = preg_replace('/\bBearer\s+[^\s,;]+/i', 'Bearer '.self::REDACTED, $value) ?? self::REDACTED;
        $value = preg_replace('/([?&](?:guest_token|token|api_key|password)=)[^&\s]+/i', '$1'.self::REDACTED, $value) ?? self::REDACTED;

        return preg_replace('/\b[0-9]{13,19}\b/', self::REDACTED, $value) ?? self::REDACTED;
    }

    public function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        return str_contains($normalized, 'password')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'authorization')
            || str_contains($normalized, 'cookie')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'card')
            || str_contains($normalized, 'cvv')
            || str_contains($normalized, 'security_code')
            || in_array($normalized, ['address', 'customer_email', 'email', 'image_path', 'payment_test_number', 'phone', 'shipping_address'], true);
    }
}
