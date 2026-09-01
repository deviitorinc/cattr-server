<?php

return [
    'enabled' => env('CATTR_CLOCK_IN_WEBHOOK_ENABLED', false),
    'url' => env('CATTR_CLOCK_IN_WEBHOOK_URL'),
    'token' => env('CATTR_CLOCK_IN_WEBHOOK_TOKEN'),
    'timezone' => env('CATTR_CLOCK_IN_WEBHOOK_TIMEZONE', 'Asia/Colombo'),
    'connect_timeout' => (int) env('CATTR_CLOCK_IN_WEBHOOK_CONNECT_TIMEOUT', 2),
    'timeout' => (int) env('CATTR_CLOCK_IN_WEBHOOK_TIMEOUT', 5),
];
