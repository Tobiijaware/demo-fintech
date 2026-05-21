<?php

return [
    'base_url' => env('SWWIPE_BASE_URL', 'https://app.swwipe.com/api/v1'),
    'timeout' => (int) env('SWWIPE_TIMEOUT', 30),
    'api_key' => env('SWWIPE_API_KEY'),
];
