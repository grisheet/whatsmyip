<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Look up the approximate location of a public IPv4 or IPv6 address.">
    <title>@yield('title', "What's My IP")</title>
    <link rel="stylesheet" href="/assets/site.css">
    @stack('scripts')
</head>
<body>
    <header class="masthead page-width">
        <a class="wordmark" href="{{ route('home', absolute: false) }}">What's My IP</a>
        <span class="byline">By Grisheet Dhage</span>
    </header>
    <main class="page-width">
        @yield('content')
    </main>
    <footer class="footer page-width">
        <nav aria-label="Site information">
            <a href="{{ route('privacy', absolute: false) }}">Privacy</a>
            <a href="{{ route('terms', absolute: false) }}">Terms</a>
        </nav>
        <div class="credits">
            <a href="https://github.com/stevebauman/location">stevebauman/location (MIT)</a>
            <a href="https://www.ip2location.io/">IP Geolocation by IP2Location.io</a>
        </div>
    </footer>
</body>
</html>
