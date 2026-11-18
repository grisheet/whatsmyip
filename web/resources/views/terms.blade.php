@extends('layouts.site')

@section('title', "Terms | What's My IP")

@section('content')
    <article class="prose">
        <h1>Terms</h1>
        <p>You may use this site to check the approximate location of a public IP address for lawful purposes. Do not use it to harass or track people, scrape or resell results, or bypass rate limits.</p>

        <h2>Accuracy and availability</h2>
        <p>IP2Location.io supplies the location estimates. Coverage varies by address and the provider's data. Do not rely on these results for emergencies, identity checks, or other decisions that require a verified location.</p>
        <p>The tool is provided as is, without warranties of accuracy, availability, or fitness for a particular purpose, to the extent allowed by law. Access may be limited when the provider is unavailable or a lookup quota has been reached.</p>
        <p>The provider retains its rights in its data. See <a href="https://www.ip2location.io/">IP2Location.io</a> for its service terms and usage conditions.</p>

        @include('contact')
    </article>
@endsection
