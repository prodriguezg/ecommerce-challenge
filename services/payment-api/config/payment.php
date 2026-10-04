<?php

return [
    'normal_delay_min_seconds' => (int) env('PAYMENT_NORMAL_DELAY_MIN_SECONDS', 2),
    'normal_delay_max_seconds' => (int) env('PAYMENT_NORMAL_DELAY_MAX_SECONDS', 8),
    'late_success_delay_seconds' => (int) env('PAYMENT_LATE_SUCCESS_DELAY_SECONDS', 150),
    'callback_url' => env('PAYMENT_CALLBACK_URL', 'http://commerce-api:8000/api/v1/payments/webhooks/mock'),
    'webhook_token' => env('PAYMENT_WEBHOOK_TOKEN'),
    'webhook_connect_timeout_seconds' => (int) env('PAYMENT_WEBHOOK_CONNECT_TIMEOUT_SECONDS', 2),
    'webhook_timeout_seconds' => (int) env('PAYMENT_WEBHOOK_TIMEOUT_SECONDS', 5),
    'worker_heartbeat_ttl_seconds' => (int) env('PAYMENT_WORKER_HEARTBEAT_TTL_SECONDS', 10),
];
