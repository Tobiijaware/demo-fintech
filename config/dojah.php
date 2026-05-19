<?php

return [
    'base_url' => env('DOJAH_BASE_URL', 'https://api.dojah.io'),
    'app_id' => env('DOJAH_APP_ID'),
    'secret_key' => env('DOJAH_SECRET_KEY'),
    'timeout' => (int) env('DOJAH_TIMEOUT', 30),
];
