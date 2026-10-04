<?php

return [
    'rate_limits' => [
        'login' => (int) env('AUTH_LOGIN_RATE_LIMIT', 5),
        'setup' => (int) env('AUTH_SETUP_RATE_LIMIT', 3),
        'registration' => (int) env('AUTH_REGISTRATION_RATE_LIMIT', 5),
    ],
];
