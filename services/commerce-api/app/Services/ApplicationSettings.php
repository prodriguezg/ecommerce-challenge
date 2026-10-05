<?php

namespace App\Services;

use App\Enums\SettingType;
use App\Models\ApplicationSetting;

class ApplicationSettings
{
    public const ReservationTimeoutSeconds = 'reservation_timeout_seconds';

    /** @return list<string> */
    public function keys(): array
    {
        return [self::ReservationTimeoutSeconds];
    }

    public function supports(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /** @return array{key: string, value: int, source: string} */
    public function effective(string $key): array
    {
        $override = ApplicationSetting::query()->where('key', $key)->first();

        return [
            'key' => $key,
            'value' => $override instanceof ApplicationSetting
                ? (int) $override->value
                : $this->environmentValue($key),
            'source' => $override instanceof ApplicationSetting ? 'database' : 'environment',
        ];
    }

    public function environmentValue(string $key): int
    {
        return match ($key) {
            self::ReservationTimeoutSeconds => (int) config('api.checkout.reservation_timeout_seconds'),
            default => throw new \InvalidArgumentException("Unknown setting key [{$key}]."),
        };
    }

    public function type(string $key): SettingType
    {
        return match ($key) {
            self::ReservationTimeoutSeconds => SettingType::Integer,
            default => throw new \InvalidArgumentException("Unknown setting key [{$key}]."),
        };
    }

    public function description(string $key): string
    {
        return match ($key) {
            self::ReservationTimeoutSeconds => 'Reservation timeout in seconds for newly created reservations.',
            default => throw new \InvalidArgumentException("Unknown setting key [{$key}]."),
        };
    }
}
