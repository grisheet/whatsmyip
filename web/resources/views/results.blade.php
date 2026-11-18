<div data-result data-state="{{ $error ? 'error' : ($values === null ? 'empty' : 'success') }}"
    data-input="{{ $input }}" data-invalid="{{ $invalid ? 'true' : 'false' }}">
    @if ($error)
        <div class="alert" id="lookup-error" role="alert">
            <strong>Lookup failed</strong>
            <p>{{ $error }}</p>
        </div>
    @endif

    <dl class="ledger">
        @foreach ($fields as $field => $label)
            <div class="ledger-row">
                <dt>{{ $label }}</dt>
                <dd @class(['data', 'missing' => ! isset($values[$field])])>{{ $values === null && ! $error ? 'Not looked up yet' : ($values[$field] ?? 'Unavailable') }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($values !== null)
        <div class="result-ip">
            <span class="result-address data">{{ $input }}</span>
            <button type="button" class="text-button" data-copy="{{ $input }}" aria-label="Copy looked-up IP" hidden>Copy</button>
        </div>
    @endif
</div>
