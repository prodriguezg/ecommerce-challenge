<?php

return [
    'checkout' => [
        'reservation_timeout_seconds' => (int) env('RESERVATION_TIMEOUT_SECONDS', 120),
        'guest_order_link_days' => (int) env('GUEST_ORDER_LINK_DAYS', 30),
        'order_number_prefix' => env('ORDER_NUMBER_PREFIX', 'ORD-'),
        'order_number_start' => (int) env('ORDER_NUMBER_START', 100001),
        'payment_url' => env('PAYMENT_API_URL', 'http://payment-api:8000/api/v1/payments'),
        'payment_callback_url' => env('PAYMENT_CALLBACK_URL', 'http://commerce-api:8000/api/v1/payments/webhooks/mock'),
        'payment_connect_timeout_seconds' => (int) env('PAYMENT_CONNECT_TIMEOUT_SECONDS', 2),
        'payment_timeout_seconds' => (int) env('PAYMENT_TIMEOUT_SECONDS', 5),
    ],
    'documentation' => [
        'enabled' => (bool) env('API_DOCS_ENABLED', false),
    ],
    'csv_import' => [
        'max_bytes' => (int) env('CSV_MAX_BYTES', 5 * 1024 * 1024),
        'max_rows' => (int) env('CSV_MAX_ROWS', 10_000),
    ],
];
