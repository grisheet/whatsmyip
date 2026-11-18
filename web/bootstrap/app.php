<?php

use App\Http\Middleware\SiteHeaders;
use App\Http\Middleware\TrustedProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(TrustProxies::class, TrustedProxies::class);
        $middleware->prepend(SiteHeaders::class);
    })
    ->withExceptions()
    ->create();
