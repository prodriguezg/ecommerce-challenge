<?php

namespace App\Enums;

use InvalidArgumentException;

enum PaymentOutcome: string
{
    case Succeeded = 'succeeded';
    case Declined = 'declined';
    case Error = 'error';

    public static function fromTestNumber(string $testNumber): self
    {
        return match ($testNumber) {
            '4000000000010001', '4000000000080004' => self::Succeeded,
            '4000000000000002' => self::Declined,
            '4000000000090003' => self::Error,
            default => throw new InvalidArgumentException('Unsupported payment test number.'),
        };
    }

    public function providerCode(): string
    {
        return match ($this) {
            self::Succeeded => 'APPROVED',
            self::Declined => 'CARD_DECLINED',
            self::Error => 'PROVIDER_ERROR',
        };
    }
}
