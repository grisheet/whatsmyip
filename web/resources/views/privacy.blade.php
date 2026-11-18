@extends('layouts.site')

@section('title', "Privacy | What's My IP")

@section('content')
    <article class="prose">
        <h1>Privacy</h1>
        <p>The site reads the IP address on your request to display your detected address and limit repeated lookups. If the site is behind a configured proxy, the site uses the client address supplied by that proxy.</p>

        <h2>Lookup data</h2>
        <p>When you submit a public address, the site checks its cache. On a cache miss, it sends the submitted address over HTTPS to IP2Location.io. Choosing “Use my IP” submits your detected address. Opening the page alone sends no lookup to the provider.</p>
        <p>The site keeps no lookup history. The server caches successful results for five minutes under a keyed hash of the queried IP. Expired entries are ineligible for reuse. The file cache may retain expired files until they are read or the cache is cleared. Rate-limit counters use a separate keyed hash for the visitor address and expire after one minute.</p>
        <p>The hosting service may log request details such as IP addresses, timestamps, and paths. IP2Location.io receives the queried address and may log or retain it under its own policies. Lookups are submitted in the request body, so the lookup address is omitted from the page URL.</p>
        <p><a href="https://www.ip2location.io/privacy-policy">IP2Location.io privacy policy</a></p>

        <h2>Cookies</h2>
        <p>Laravel uses a session cookie named <code>whatsmyip_session</code> and may set an <code>XSRF-TOKEN</code> cookie for forgery protection. The session expires after 30 minutes of inactivity with the default configuration. The session does not store lookup results or submitted IP addresses. The site uses no analytics or advertising cookies.</p>

        @include('contact')
    </article>
@endsection
