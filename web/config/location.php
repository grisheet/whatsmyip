<?php

use Stevebauman\Location\Drivers\Ip2locationio;

return [
    'driver' => Ip2locationio::class,
    'fallbacks' => [],
    'testing' => ['enabled' => false],
    'total_timeout' => 4,
    'http' => [
        'timeout' => 4,
        'connect_timeout' => 2,
        'allow_redirects' => false,
    ],
    'ip2locationio' => ['token' => env('IP2LOCATIONIO_TOKEN')],
];
