<?php

namespace App\Http\Controllers;

use ErrorException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Stevebauman\Location\LocationManager;
use Stevebauman\Location\Position;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use TypeError;

class LookupController
{
    private const FIELDS = [
        'countryName' => 'Country',
        'regionName' => 'Region',
        'cityName' => 'City',
        'postalCode' => 'Postal code',
        'timezone' => 'Time zone',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
    ];

    private const SPECIAL_NETWORKS = [
        '192.0.0.0/24', '192.88.99.0/24', '224.0.0.0/4',
        '2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20',
    ];

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    public function lookup(Request $request, LocationManager $location): Response
    {
        $visitor = $request->ip() ?? '';
        $input = $request->input('source') === 'visitor' ? $visitor : $request->input('ip');
        $input = is_string($input) ? trim($input) : '';
        $display = substr($input, 0, 45);

        $limit = $this->consume($this->key('visitor', $visitor), config('site.requests_per_minute'), 60);
        if ($limit > 0) {
            return $this->page($request, $display, status: 429, error: 'Too many lookup requests. Wait a minute and try again.', retryAfter: $limit);
        }

        if (strlen($input) > 45 || ! filter_var($input, FILTER_VALIDATE_IP)) {
            return $this->page($request, $display, status: 422, error: 'Enter a valid IPv4 or IPv6 address.', invalid: true);
        }

        if (! $this->isPublic($input)) {
            return $this->page($request, $display, status: 422, error: 'Private and reserved addresses have no public location. Enter a public IP address.', invalid: true);
        }

        $ip = inet_ntop(inet_pton($input));
        if (! config('location.ip2locationio.token')) {
            return $this->page($request, $ip, status: 503, error: 'The location provider is currently unavailable. Try again later.');
        }

        $cacheKey = $this->key('result', $ip);
        $values = Cache::get($cacheKey);

        try {
            if ($values === null) {
                // Keep simultaneous requests for one address from spending
                // several provider calls on the same cache miss.
                $values = Cache::lock($cacheKey.':lock', 10)->block(5, function () use ($location, $ip, $cacheKey) {
                    if (($cached = Cache::get($cacheKey)) !== null) {
                        return $cached;
                    }

                    $retry = $this->consume('lookup:provider', config('site.provider_requests_per_day'), 86400);
                    if ($retry > 0) {
                        return $retry;
                    }

                    try {
                        $position = $location->get($ip);
                    } catch (ErrorException|TypeError) {
                        return null;
                    }

                    if ($position === false) {
                        return null;
                    }

                    $values = $this->values($position);
                    if (array_filter($values, static fn ($value) => $value !== null) === []) {
                        return null;
                    }

                    Cache::put($cacheKey, $values, config('site.cache_seconds'));

                    return $values;
                });
            }
        } catch (LockTimeoutException) {
            $values = null;
        }

        if (is_int($values)) {
            return $this->page($request, $ip, status: 429, error: 'The site has reached its daily lookup limit. Try again later.', retryAfter: $values);
        }

        if ($values === null) {
            return $this->page($request, $ip, status: 502, error: 'The location provider did not return a usable result. Try again later or try another address.');
        }

        return $this->page($request, $ip, $values);
    }

    private function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false
            && ! IpUtils::checkIp($ip, self::SPECIAL_NETWORKS)
            && (! str_contains($ip, ':') || IpUtils::checkIp($ip, '2000::/3'));
    }

    private function key(string $purpose, string $value): string
    {
        return 'lookup:'.$purpose.':'.hash_hmac('sha256', $value, config('app.key'));
    }

    private function consume(string $key, int $limit, int $seconds): int
    {
        try {
            return Cache::lock($key.':count', 3)->block(1, function () use ($key, $limit, $seconds): int {
                if (RateLimiter::tooManyAttempts($key, $limit)) {
                    return max(1, RateLimiter::availableIn($key));
                }

                RateLimiter::hit($key, $seconds);

                return 0;
            });
        } catch (LockTimeoutException) {
            return 1;
        }
    }

    private function values(Position $position): array
    {
        $values = array_intersect_key($position->toArray(), self::FIELDS);
        $values['countryName'] ??= $position->countryCode;

        return array_map(static function (?string $value): ?string {
            $value = trim((string) $value);

            return in_array(strtolower($value), ['', '-', 'unknown'], true) ? null : $value;
        }, $values);
    }

    private function page(
        Request $request,
        string $input = '',
        ?array $values = null,
        int $status = 200,
        ?string $error = null,
        ?int $retryAfter = null,
        bool $invalid = false,
    ): Response {
        $partial = $request->isMethod('POST') && $request->header('X-Lookup-Fragment') === '1';

        return response()->view($partial ? 'results' : 'index', [
            'visitor' => $request->ip(),
            'input' => $input,
            'values' => $values,
            'error' => $error,
            'invalid' => $invalid,
            'fields' => self::FIELDS,
        ], $status, $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter]);
    }
}
