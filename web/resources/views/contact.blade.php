@if (filter_var(config('site.contact'), FILTER_VALIDATE_EMAIL))
    <h2>Contact</h2>
    <p>Send questions to <a href="mailto:{{ config('site.contact') }}">{{ config('site.contact') }}</a>.</p>
@endif
