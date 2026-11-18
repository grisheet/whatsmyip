const form = document.querySelector('#lookup-form');
const input = form.elements.ip;
const results = document.querySelector('#lookup-results');
const alert = document.querySelector('#lookup-error');
const status = document.querySelector('#lookup-status');
const visitor = document.querySelector('#visitor-ip');
const visitorCopy = form.querySelector('button[type="button"]');
const resultIp = results.querySelector('.result-ip');
const resultCopy = resultIp.querySelector('button');
const fields = [...results.querySelectorAll('[data-field]')];
const submitButtons = [...form.querySelectorAll('[type="submit"]')];
const endpoint = 'https://api.ipapi.is/';
const cache = new Map();
const cacheLifetime = 5 * 60 * 1000;
let detection;

function parseAddress(text) {
    if (text.includes(':')) {
        if (!/^[0-9a-f:.]{2,45}$/i.test(text)) return null;
        try {
            return new URL('https://[' + text + ']/').hostname.slice(1, -1);
        } catch {
            return null;
        }
    }

    const octets = text.split('.');
    if (octets.length !== 4 || !octets.every(part =>
        /^(0|[1-9][0-9]{0,2})$/.test(part) && Number(part) <= 255
    )) return null;
    return text;
}

function isPublic(ip) {
    if (ip.includes(':')) {
        if (!/^[23][0-9a-f]{3}:/.test(ip)) return false;

        const [prefix, block = '0'] = ip.split(':');
        const second = parseInt(block || '0', 16);
        return !(prefix === '2001' && (second < 0x200 || second === 0xdb8))
            && prefix !== '2002' && !(prefix === '3fff' && second < 0x1000);
    }

    const [a, b, c] = ip.split('.').map(Number);
    return a > 0 && a < 224 && a !== 10 && a !== 127
        && !(a === 100 && b >= 64 && b <= 127)
        && !(a === 169 && b === 254)
        && !(a === 172 && b >= 16 && b <= 31)
        && !(a === 192 && (b === 168 || (b === 0 && (c === 0 || c === 2)) || (b === 88 && c === 99)))
        && !(a === 198 && (b === 18 || b === 19 || (b === 51 && c === 100)))
        && !(a === 203 && b === 0 && c === 113);
}

function setCopy(button, ip) {
    button.dataset.ip = ip;
    button.hidden = !navigator.clipboard;
}

for (const button of [visitorCopy, resultCopy]) {
    const label = button.getAttribute('aria-label');
    let timer;
    button.addEventListener('click', async () => {
        clearTimeout(timer);
        try {
            await navigator.clipboard.writeText(button.dataset.ip);
            button.textContent = 'Copied';
            button.setAttribute('aria-label', label.replace(/^Copy/, 'Copied'));
            status.textContent = 'IP address copied.';
        } catch {
            button.textContent = 'Copy failed';
            button.setAttribute('aria-label', 'Copy failed');
            status.textContent = 'Could not copy the IP address. Select it to copy it.';
        }
        timer = setTimeout(() => {
            button.textContent = 'Copy';
            button.setAttribute('aria-label', label);
        }, 1800);
    });
}

function render(location) {
    for (const field of fields) {
        const value = location?.[field.dataset.field];
        const available = (typeof value === 'string' && value.trim() !== '' && value !== '-')
            || (typeof value === 'number' && Number.isFinite(value));
        field.textContent = available ? value : 'Unavailable';
        field.classList.toggle('missing', !available);
    }
}

function showError(message, invalid = false) {
    render(null);
    resultIp.hidden = true;
    alert.querySelector('p').textContent = message;
    alert.hidden = false;
    if (invalid) {
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', 'lookup-error');
    }
}

async function lookup(ip) {
    const cached = ip ? cache.get(ip) : detection;
    if (cached && cached.expires > Date.now()) return cached;

    const url = new URL(endpoint);
    if (ip) url.searchParams.set('q', ip);
    const response = await fetch(url, {
        credentials: 'omit',
        cache: 'no-store',
        signal: AbortSignal.timeout(7000),
    });
    if (response.status === 429) throw new Error('quota');
    if (!response.ok) throw new Error('provider');

    const data = await response.json();
    const queriedIp = typeof data?.ip === 'string' ? parseAddress(data.ip) : null;
    if (!queriedIp || !isPublic(queriedIp) || (ip && queriedIp !== ip) || data.error || !data.location) {
        if (typeof data?.error === 'string' && /rate limit|quota|too many/i.test(data.error)) throw new Error('quota');
        throw new Error('provider');
    }

    const location = {...data.location, zip: data.location.zip ?? data.location.postal};
    const entry = { ip: queriedIp, location, expires: Date.now() + cacheLifetime };
    cache.set(queriedIp, entry);
    if (!ip) detection = entry;
    return entry;
}

form.addEventListener('submit', async event => {
    event.preventDefault();
    if (results.getAttribute('aria-busy') === 'true') return;

    alert.hidden = true;
    input.removeAttribute('aria-invalid');
    input.removeAttribute('aria-describedby');
    const useVisitor = event.submitter?.value === 'visitor';
    const ip = useVisitor ? null : parseAddress(input.value.trim());

    if (!useVisitor && !ip) return showError('Enter a valid IPv4 or IPv6 address.', true);
    if (ip && !isPublic(ip)) {
        return showError('Private and reserved addresses have no public location. Enter a public IP address.', true);
    }

    results.setAttribute('aria-busy', 'true');
    submitButtons.forEach(button => button.setAttribute('aria-disabled', 'true'));
    status.textContent = 'Looking up the IP address.';

    try {
        const entry = await lookup(ip);
        if (useVisitor) {
            visitor.textContent = entry.ip;
            form.querySelector('.live-dot').hidden = false;
            setCopy(visitorCopy, entry.ip);
        }
        input.value = entry.ip;
        render(entry.location);
        resultIp.querySelector('.result-address').textContent = entry.ip;
        resultIp.hidden = false;
        setCopy(resultCopy, entry.ip);
        status.textContent = 'Location details updated for ' + entry.ip + '.';
    } catch (error) {
        status.textContent = '';
        showError(error.message === 'quota'
            ? 'The location provider has reached its lookup limit. Try again later.'
            : 'The location provider could not complete the lookup. Try again later or try another address.');
    } finally {
        results.setAttribute('aria-busy', 'false');
        submitButtons.forEach(button => button.removeAttribute('aria-disabled'));
    }
});

input.disabled = false;
submitButtons.forEach(button => { button.disabled = false; });
