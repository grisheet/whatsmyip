<?php

return [
    'contact' => env('SITE_CONTACT_EMAIL'),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
    'cache_seconds' => 300,
    'requests_per_minute' => max(1, (int) env('LOOKUP_REQUESTS_PER_MINUTE', 10)),
    'provider_requests_per_day' => max(1, (int) env('LOOKUP_PROVIDER_REQUESTS_PER_DAY', 100)),
];
