const form = document.querySelector('#lookup-form');
const results = document.querySelector('#lookup-results');
const content = document.querySelector('#result-content');
const status = document.querySelector('#lookup-status');
const ip = form.elements.ip;

function enableCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach(button => {
        button.hidden = !navigator.clipboard;
    });
}

enableCopyButtons();

document.addEventListener('click', async event => {
    const button = event.target.closest('button[data-copy]');
    if (!button) return;

    const label = button.dataset.copyLabel ??= button.getAttribute('aria-label');
    clearTimeout(button.copyTimer);

    try {
        await navigator.clipboard.writeText(button.dataset.copy);
        button.textContent = 'Copied';
        button.setAttribute('aria-label', label.replace(/^Copy/, 'Copied'));
        status.textContent = 'IP address copied.';
    } catch {
        button.textContent = 'Copy failed';
        button.setAttribute('aria-label', 'Copy failed');
        status.textContent = 'Could not copy the IP address. Select the address to copy it.';
    }

    button.copyTimer = setTimeout(() => {
        button.textContent = 'Copy';
        button.setAttribute('aria-label', label);
    }, 1800);
});

form.addEventListener('submit', async event => {
    event.preventDefault();
    if (results.getAttribute('aria-busy') === 'true') return;

    const data = new URLSearchParams(new FormData(form, event.submitter));
    const buttons = form.querySelectorAll('button[type="submit"]');
    results.setAttribute('aria-busy', 'true');
    buttons.forEach(button => button.setAttribute('aria-disabled', 'true'));
    status.textContent = 'Looking up the IP address.';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {'X-Lookup-Fragment': '1'},
            body: data,
        });
        const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
        const next = documentFragment.querySelector('[data-result]');
        if (!next) throw new Error('Missing result');

        content.replaceChildren(next);
        ip.value = next.dataset.input;
        if (next.dataset.invalid === 'true') {
            ip.setAttribute('aria-invalid', 'true');
            ip.setAttribute('aria-describedby', 'lookup-error');
        } else {
            ip.removeAttribute('aria-invalid');
            ip.removeAttribute('aria-describedby');
        }

        enableCopyButtons();
        status.textContent = next.dataset.state === 'success'
            ? 'Location details updated for ' + next.dataset.input + '.'
            : '';
    } catch {
        const existing = content.querySelector('#lookup-error');
        if (existing) existing.remove();
        ip.removeAttribute('aria-invalid');
        ip.removeAttribute('aria-describedby');

        const alert = document.createElement('div');
        alert.className = 'alert';
        alert.setAttribute('role', 'alert');
        const title = document.createElement('strong');
        title.textContent = 'Lookup failed';
        const message = document.createElement('p');
        message.textContent = 'The site could not complete the request. Reload the page and try again.';
        alert.append(title, message);
        content.prepend(alert);
        status.textContent = '';
    } finally {
        results.setAttribute('aria-busy', 'false');
        buttons.forEach(button => button.removeAttribute('aria-disabled'));
    }
});
