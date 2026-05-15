<?php

declare(strict_types=1);

return [

    'freecurrencyapi' => [
        'api_key' => env('FREECURRENCYAPI_KEY', ''),
        'base_url' => env('FREECURRENCYAPI_BASE_URL', 'https://api.freecurrencyapi.com'),
        'base_currency' => env('FREECURRENCYAPI_BASE_CURRENCY', 'USD'),
        'timeout_seconds' => (int) env('FREECURRENCYAPI_TIMEOUT', 10),
    ],

    'admin' => [
        'username' => env('ADMIN_USERNAME', 'admin'),
        'password' => env('ADMIN_PASSWORD', 'Aqaz'),
    ],

];
