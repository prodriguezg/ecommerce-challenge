<?php

return [
    'rate_limits' => [
        'login' => (int) env('AUTH_LOGIN_RATE_LIMIT', 5),
        'setup' => (int) env('AUTH_SETUP_RATE_LIMIT', 3),
        'customer_registration' => (int) env('AUTH_CUSTOMER_REGISTRATION_RATE_LIMIT', 5),
        'guest_registration' => (int) env('AUTH_GUEST_REGISTRATION_RATE_LIMIT', 5),
    ],
];
