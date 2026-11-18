<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustedProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        // Symfony walks the forwarding chain from the nearest hop and
        // returns the nearest client address outside these trusted networks.
        Request::setTrustedProxies(
            config('site.trusted_proxies'),
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        return $next($request);
    }
}
