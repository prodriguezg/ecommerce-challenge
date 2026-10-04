<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Requested = 'requested';
    case Processing = 'processing';
    case InitiationFailed = 'initiation_failed';
    case Succeeded = 'succeeded';
    case Declined = 'declined';
    case ProviderError = 'provider_error';
}
