@extends('layouts.site')

@push('scripts')
    <script src="/assets/lookup.js" defer></script>
@endpush

@section('content')
    <div class="lookup">
        <section class="controls" aria-labelledby="lookup-title">
            <h1 id="lookup-title">Look up an<br class="desktop-break"> IP address</h1>
            <p class="intro">Enter a public IPv4 or IPv6 address to see its approximate location.</p>

            <form id="lookup-form" method="post" action="{{ route('lookup', absolute: false) }}">
                @csrf
                <label for="ip">IP address</label>
                <div class="input-row">
                    <input id="ip" name="ip" type="text" value="{{ $input }}" maxlength="45"
                        placeholder="IPv4 or IPv6" autocomplete="off" autocapitalize="none" spellcheck="false"
                        @if ($invalid) aria-invalid="true" aria-describedby="lookup-error" @endif>
                    <button class="primary" type="submit" name="source" value="manual">Look up</button>
                </div>

                <div class="visitor">
                    <div class="visitor-heading">
                        <span class="visitor-label">
                            @if ($visitor)<span class="live-dot" aria-hidden="true"></span>@endif
                            Your detected IP
                        </span>
                        @if ($visitor)
                            <button class="text-button" type="submit" name="source" value="visitor">Use my IP</button>
                        @endif
                    </div>
                    <div class="visitor-data">
                        <span class="data">{{ $visitor ?? 'Unavailable' }}</span>
                        @if ($visitor)
                            <button class="text-button" type="button" data-copy="{{ $visitor }}" aria-label="Copy detected IP" hidden>Copy</button>
                        @endif
                    </div>
                </div>
            </form>
        </section>

        <section class="results" aria-labelledby="result-title" id="lookup-results" aria-busy="false">
            <h2 id="result-title">Location details</h2>
            <div id="result-content">
                @include('results')
            </div>
        </section>
    </div>

    <p id="lookup-status" class="visually-hidden" role="status" aria-atomic="true"></p>
    <section class="accuracy" aria-labelledby="accuracy-title">
        <h2 id="accuracy-title">How accurate is IP location?</h2>
        <p>IP2Location.io estimates a network's location. You may see the area of an internet provider, mobile network, or VPN exit. An IP address cannot pinpoint someone's home or provide a GPS location.</p>
    </section>
@endsection
