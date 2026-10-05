<?php

return [
    'checkout' => [
        'reservation_timeout_seconds' => (int) env('RESERVATION_TIMEOUT_SECONDS', 120),
        'guest_order_link_days' => (int) env('GUEST_ORDER_LINK_DAYS', 30),
        'guest_order_rate_limit' => (int) env('GUEST_ORDER_RATE_LIMIT', 60),
        'order_number_prefix' => env('ORDER_NUMBER_PREFIX', 'ORD-'),
        'order_number_start' => (int) env('ORDER_NUMBER_START', 100001),
        'payment_url' => env('PAYMENT_API_URL', 'http://payment-api:8000/api/v1/payments'),
        'payment_callback_url' => env('PAYMENT_CALLBACK_URL', 'http://commerce-api:8000/api/v1/payments/webhooks/mock'),
        'payment_connect_timeout_seconds' => (int) env('PAYMENT_CONNECT_TIMEOUT_SECONDS', 2),
        'payment_timeout_seconds' => (int) env('PAYMENT_TIMEOUT_SECONDS', 5),
        'payment_webhook_token' => env('PAYMENT_WEBHOOK_TOKEN'),
        'payment_webhook_rate_limit' => (int) env('PAYMENT_WEBHOOK_RATE_LIMIT', 120),
        'reservation_expiration_batch_size' => (int) env('RESERVATION_EXPIRATION_BATCH_SIZE', 100),
        'reservation_claim_ttl_seconds' => (int) env('RESERVATION_CLAIM_TTL_SECONDS', 60),
        'reservation_worker_interval_seconds' => (int) env('RESERVATION_WORKER_INTERVAL_SECONDS', 5),
    ],
    'documentation' => [
        'enabled' => (bool) env('API_DOCS_ENABLED', false),
    ],
    'csv_import' => [
        'max_bytes' => (int) env('CSV_MAX_BYTES', 5 * 1024 * 1024),
        'max_rows' => (int) env('CSV_MAX_ROWS', 10_000),
    ],
    'rate_limits' => [
        'checkout' => (int) env('CHECKOUT_RATE_LIMIT', 30),
        'csv_upload' => (int) env('CSV_UPLOAD_RATE_LIMIT', 5),
        'image_upload' => (int) env('IMAGE_UPLOAD_RATE_LIMIT', 10),
    ],
];
