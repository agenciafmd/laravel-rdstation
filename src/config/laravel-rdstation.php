<?php

return [
    // v1
    'public_key' => env('RDSTATION_PUBLIC_KEY', ''),

    // v2
    'client_id' => env('RDSTATION_CLIENT_ID', ''),
    'client_secret' => env('RDSTATION_CLIENT_SECRET', ''),
    'refresh_token' => env('RDSTATION_REFRESH_TOKEN', ''),

    'error_email' => env('RDSTATION_ERROR_EMAIL', ''),
];
