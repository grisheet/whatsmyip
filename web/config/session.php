<?php

return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_LIFETIME', 30),
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'lottery' => [2, 100],
    'cookie' => 'whatsmyip_session',
    'path' => '/',
    'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production'),
    'http_only' => true,
    'same_site' => 'lax',
];
