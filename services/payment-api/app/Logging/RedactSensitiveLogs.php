<?php

namespace App\Logging;

use App\Support\SensitiveDataRedactor;
use Illuminate\Log\Logger;
use Monolog\LogRecord;

final class RedactSensitiveLogs
{
    public function __construct(private readonly SensitiveDataRedactor $redactor) {}

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
            message: $this->redactor->redactText($record->message),
            context: $this->redactor->redactArray($record->context),
            extra: $this->redactor->redactArray($record->extra),
        ));
    }
}
