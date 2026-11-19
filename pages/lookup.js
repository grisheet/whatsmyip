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
const fieldNames = ['country', 'state', 'city', 'zip', 'timezone', 'latitude', 'longitude'];
const dataDirectory = new URL('./data/', import.meta.url);
let shardIndex;
let lastShard;
let detectedIp;

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

function addressNumber(ip) {
    if (!ip.includes(':')) {
        return ip.split('.').reduce((number, octet) => (number << 8n) + BigInt(octet), 0n);
    }

    const [left, right] = ip.split('::');
    const groups = left ? left.split(':') : [];
    if (right !== undefined) {
        const tail = right ? right.split(':') : [];
        groups.push(...Array(8 - groups.length - tail.length).fill('0'), ...tail);
    }
    return groups.reduce((number, group) => (number << 16n) + BigInt('0x' + group), 0n);
}

async function getFile(filename) {
    const response = await fetch(new URL(filename, dataDirectory), {signal: AbortSignal.timeout(10000)});
    if (!response.ok) throw new Error('data');
    return response;
}

async function readShard(name) {
    if (!shardIndex) {
        const response = await getFile('index.json');
        shardIndex = new Set(await response.json());
    }
    if (!shardIndex.has(name)) return null;
    if (lastShard?.name === name) return lastShard.data;

    const response = await getFile(name + '.bin');
    const stream = response.body.pipeThrough(new DecompressionStream('gzip'));
    const data = await new Response(stream).json();
    lastShard = {name, data};
    return data;
}

async function lookup(ip) {
    const v6 = ip.includes(':');
    const hostBits = v6 ? 116n : 22n;
    const number = addressNumber(ip);
    const prefix = number >> hostBits;
    const name = (v6 ? '6-' : '4-') + prefix.toString(16).padStart(3, '0');
    const shard = await readShard(name);
    if (!shard) return null;

    const [locations, intervals] = shard;
    const offset = number - (prefix << hostBits);
    let low = 0;
    let high = intervals.length - 1;
    while (low <= high) {
        const middle = Math.floor((low + high) / 2);
        const [start, end, location] = intervals[middle];
        if (offset < BigInt('0x' + start)) {
            high = middle - 1;
        } else if (offset > BigInt('0x' + end)) {
            low = middle + 1;
        } else {
            return Object.fromEntries(fieldNames.map((field, index) => [field, locations[location][index]]));
        }
    }
    return null;
}

async function detectAddress() {
    if (detectedIp) return detectedIp;

    try {
        const response = await fetch('https://api64.ipify.org/?format=json', {
            credentials: 'omit',
            cache: 'no-store',
            signal: AbortSignal.timeout(7000),
        });
        if (!response.ok) throw new Error();
        const {ip} = await response.json();
        const address = typeof ip === 'string' ? parseAddress(ip) : null;
        if (!address || !isPublic(address)) throw new Error();
        detectedIp = address;
        return address;
    } catch {
        throw new Error('detection');
    }
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
        const address = useVisitor ? await detectAddress() : ip;
        if (useVisitor) {
            visitor.textContent = address;
            form.querySelector('.live-dot').hidden = false;
            setCopy(visitorCopy, address);
        }
        input.value = address;

        const location = await lookup(address);
        if (!location) {
            status.textContent = '';
            showError('No location is available for this address in the public dataset.');
            return;
        }

        render(location);
        resultIp.querySelector('.result-address').textContent = address;
        resultIp.hidden = false;
        setCopy(resultCopy, address);
        status.textContent = 'Location details updated for ' + address + '.';
    } catch (error) {
        status.textContent = '';
        showError(error.message === 'detection'
            ? 'Your public address could not be detected. You can enter a public IP address manually.'
            : 'The location data could not be loaded. Reload this page and try again.');
    } finally {
        results.setAttribute('aria-busy', 'false');
        submitButtons.forEach(button => button.removeAttribute('aria-disabled'));
    }
});

input.disabled = false;
submitButtons.forEach(button => { button.disabled = false; });
