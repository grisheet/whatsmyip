# What's My IP

A public IP-location tool by Grisheet Dhage. The standalone Laravel website lives in [web/](web/). It uses the [stevebauman/location](https://github.com/stevebauman/location) package in this repository through a Composer path dependency. The original package documentation follows below, and its [MIT license](LICENSE) remains in place.

## Run on a Mac

Install [Homebrew](https://brew.sh/) if you do not have it. Then open Terminal and run:

```sh
brew install php@8.4 composer
export PATH="$(brew --prefix php@8.4)/bin:$PATH"

git clone https://github.com/grisheet/whatsmyip.git
cd whatsmyip
git switch web/public-ip-lookup
cd web

composer install
cp .env.example .env
php artisan key:generate
```

The branch must be available on GitHub for `git switch` to find it. If you already have the review checkout, start from its `web/` directory and keep your existing `.env` if present. Repeat the `export PATH` command when opening a new shell.

Edit `web/.env` and set `IP2LOCATIONIO_TOKEN` to your provider key. Then run the development server from `web/`:

```sh
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000` in your browser. The detected address will usually be `127.0.0.1`. To exercise a provider lookup locally, type a public address. The application renders without a provider key and returns an unavailable message when you ask for a public lookup.

Run the website tests from `web/` with `composer test`. They use fake provider responses and in-memory cache and sessions. Run the upstream package suite separately from the repository root with `composer install` and `vendor/bin/pest`.

## Location provider and launch review

The server uses the package's `Ip2locationio` driver to query `https://api.ip2location.io/` for public IPv4 and IPv6 addresses. It requires an API key and has no fallback provider or sample IP substitution. The connection timeout is two seconds and the lookup budget is four seconds. Coverage and fields depend on the address and plan.

The [official PHP SDK documentation](https://github.com/ip2location/ip2location-io-php#api-access-and-query-limits) lists 50,000 monthly IP geolocation queries for its keyed Free plan and requires attribution. The [API documentation](https://www.ip2location.io/ip2location-documentation) and [pricing page](https://www.ip2location.io/pricing) are the sources for current scope and quotas. Confirm with IP2Location.io that your plan permits a public lookup website, displaying the returned fields, and five-minute caching. Verify any required attribution wording before launch. The live plan and terms pages could not be checked from the restricted cloud workspace. The site includes a linked provider credit in its footer.

Successful results are reusable for five minutes. The site has no lookup history. The filesystem cache may retain expired entries until accessed or cleared. The app defaults to ten requests per visitor address per minute and 100 upstream attempts per 24-hour window per instance. Cache hits do not spend the upstream quota. Set a provider-side cap if your plan offers one. Replacing the container can reset local counters, and adding replicas multiplies the upstream budget.

Review the site's [Privacy](web/resources/views/privacy.blade.php) and [Terms](web/resources/views/terms.blade.php) pages before launch. They describe the implementation and are not legal advice. Check your host and provider's actual logging and retention policies, and add a public contact email you can monitor.

## Deploy with Docker and HTTPS

Build the Docker image with the repository root as the context:

```sh
docker build -t whatsmyip .
```

The image installs locked production dependencies and retains the local package alongside `web/`. It starts Apache with `start-whatsmyip`, listens on `0.0.0.0` with `PORT` defaulting to `8080`, and uses `/srv/whatsmyip/web/public` as its document root. The app's `storage/` and `bootstrap/cache/` directories must be writable by the web process. The image sets their permissions, excludes `.env` files from the build, and supports a `GET /up` health check.

### Required settings

| Variable | Value |
| --- | --- |
| `APP_ENV` | `production`, set by the image |
| `APP_DEBUG` | `false`, set by the image |
| `APP_URL` | The public HTTPS URL, such as `https://ip.example.com` |
| `APP_KEY` | A unique key from `php artisan key:generate --show` run in `web/`. Keep it secret and stable across deployments. |
| `IP2LOCATIONIO_TOKEN` | Your provider key. Set it at runtime, never as a Docker build argument. |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS, set by the image |
| `TRUSTED_PROXIES` | Exact trusted proxy IPs or CIDRs separated by commas. Leave it blank for a direct connection. |
| `SITE_CONTACT_EMAIL` | Optional public email shown on the legal pages |
| `LOOKUP_REQUESTS_PER_MINUTE` | Per-visitor limit, default `10` |
| `LOOKUP_PROVIDER_REQUESTS_PER_DAY` | Per-instance upstream limit, default `100`. Set it below your provider allowance. |

The app uses the connection address unless the immediate peer is in `TRUSTED_PROXIES`. It then reads `X-Forwarded-For` and `X-Forwarded-Proto` from that trust boundary. Your edge must overwrite untrusted headers or append the actual client address. Trust only the proxy hops you control, and do not expose a direct path that bypasses the proxy. Do not use catch-all CIDRs. After deployment, check that the detected address matches your connection from two different networks.

### Docker host with Caddy

Use any Linux host with Docker Engine and Docker Compose v2. A VM and domain may have recurring charges. Point your domain's DNS to the host and open incoming TCP ports 80 and 443. Clone the reviewed branch on the host, then run these commands at the repository root:

```sh
cp web/deploy/site.env.example .env.deploy
chmod 600 .env.deploy
docker run --rm --entrypoint php whatsmyip artisan key:generate --show
```

Run `docker build -t whatsmyip .` first if the image is not present. Paste the generated key into `.env.deploy`, then fill in `SITE_DOMAIN` without a URL scheme, the provider key, and your contact email. Start the site:

```sh
docker compose --env-file .env.deploy up -d --build
docker compose --env-file .env.deploy ps
curl -fsS https://ip.example.com/up
```

Replace `ip.example.com` with your domain. Caddy obtains and renews HTTPS certificates and forwards traffic to Apache over a private Docker network. Only Caddy publishes ports, and the application trusts only Caddy's assigned address. If the example Docker subnet overlaps a network on your host, choose a free private subnet and update the allocation range, Caddy's static IP, and `TRUSTED_PROXIES` in `compose.yaml`.

### Railway option

Create a service from this GitHub repository and select the reviewed branch. In the service settings, keep the root directory at the repository root. Select the root `Dockerfile`, or set `RAILWAY_DOCKERFILE_PATH=Dockerfile` if a path override is needed. Leave the start-command override blank so Railway runs the image's default command. Add the runtime variables above in the service settings. Set the health check path to `/up`.

Use Railway's public HTTP networking to generate an HTTPS domain, with the target port matching `PORT` (or `8080` if you set no port). Update `APP_URL` to the generated URL and redeploy. For client IP detection, confirm Railway's documented forwarding behavior and which ingress addresses can be safely trusted. If you cannot identify the trusted peers, use the Caddy deployment above for correct client-IP detection and per-visitor limits.

The [Railway Dockerfile guide](https://docs.railway.com/guides/dockerfiles), [public networking guide](https://docs.railway.com/guides/public-networking), and [pricing page](https://railway.com/pricing) cover platform settings and charges. The live Railway docs and pricing were unavailable from this cloud workspace. Check the current plan and usage costs before creating resources. The production image has been tested locally, and the site has not been published to a public host.

## Credits

Website fonts are self-hosted Latin WOFF2 files distributed by Fontsource 5.3.0. The [font directory](web/public/assets/fonts/) includes the SIL Open Font License files for Instrument Serif, Hanken Grotesk, and JetBrains Mono.

## Upstream package documentation

<h1 align="center">Location</h1>

<p align="center">
Retrieve a visitor's location from their IP address using various services (online & local).
</p>

<p align="center">
<a href="https://github.com/stevebauman/location/actions"><img src="https://img.shields.io/github/actions/workflow/status/stevebauman/location/run-tests.yml?branch=master&style=flat-square" alt="Build status"></a>
<a href="https://packagist.org/packages/stevebauman/location"><img src="https://img.shields.io/packagist/dt/stevebauman/location.svg?style=flat-square" alt="Number of downloads"></a>
<a href="https://packagist.org/packages/stevebauman/location"><img src="https://img.shields.io/packagist/l/stevebauman/location.svg?style=flat-square" alt="MIT license"></a>
</p>

## Index

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Testing](#testing)
- [Drivers](#drivers)
- [Versioning](#versioning)

## Requirements

- PHP >= 8.1
- Laravel >= 8.0

## Installation

Install location using `composer require`:

```bash
composer require stevebauman/location
```

Publish the configuration file (this will create a `location.php` file inside the `config` directory):

```bash
php artisan vendor:publish --provider="Stevebauman\Location\LocationServiceProvider"
```

## Usage

### Retrieve a client's location

```php
use Stevebauman\Location\Facades\Location;

if ($position = Location::get()) {
    // Successfully retrieved position.
    echo $position->countryName;
} else {
    // Failed retrieving position.
}
```

> **Important**:
> - This method retrieves the user's IP address via `request()->ip()`.
> - In the default configuration, `testing.enabled` is active, the returned IP address is in the USA. Disable it to get the client's real IP address.

`Location::get()` returns a `Stevebauman\Location\Position` instance, or `false` when no driver could
locate the IP address. With the default `IpApi` driver, the position looks like this:

```php
$position = Location::get('8.8.8.8');

$position->toArray();
```

```php
[
    'ip' => '8.8.8.8',
    'driver' => 'Stevebauman\Location\Drivers\IpApi',
    'countryName' => 'United States',
    'currencyCode' => 'USD',
    'countryCode' => 'US',
    'regionCode' => 'VA',
    'regionName' => 'Virginia',
    'cityName' => 'Ashburn',
    'zipCode' => '20149',
    'isoCode' => null,
    'postalCode' => '20149',
    'latitude' => '39.03',
    'longitude' => '-77.5',
    'metroCode' => null,
    'areaCode' => null,
    'timezone' => 'America/New_York',
]
```

All values are strings, including `latitude` and `longitude`. A field the driver
doesn't provide is `null`. See [Available Fields](#available-fields) for what each driver returns.

### Retrieve the location of a specific IP address

```php
$position = Location::get('8.8.8.8');
```

## Testing

You may call `Location::fake` with an array of IP address patterns and their expected positions to fake the location of an IP address:

```php
use Stevebauman\Location\Position;
use Stevebauman\Location\Facades\Location;

Location::fake([
    '127.0.0.1' => Position::make([
        'countryName' => 'United States',
        'countryCode' => 'US',
        // ...
    ])
]);

// Somewhere in your application...

$position = Location::get('127.0.0.1'); // Position
```

If you prefer, you may use an asterisk to return the same position for any IP address that is given: 

```php
Location::fake([
    '*' => Position::make([
        'countryName' => 'United States',
        'countryCode' => 'US',
        // ...
    ])
]);

$position = Location::get($anyIpAddress); // Position
```

If no expectations are given, or an expectation is not matched, `Location::get` will return `false`:

```php
Location::fake();

Location::get($anyIpAddress); // false
```

If your application attempts to retrieve the location's of multiple IP addresses, you may provide multiple IP address expectation patterns:

```php
Location::fake([
    '127.0.0.1' => Position::make([
        'countryName' => 'United States',
        'countryCode' => 'US',
        // ...
    ]),
    '192.168.1.1' => Position::make([
        'countryName' => 'Canada',
        'countryCode' => 'CA',
        // ...
    ]),
]);
```

You may also use an asterisk to fake several IP patterns:

```php
Location::fake([
    '192.123.*.*' => Position::make([
        'countryName' => 'United States',
        'countryCode' => 'US',
        // ...
    ]),
    '192.456.*.*' => Position::make([
        'countryName' => 'Canada',
        'countryCode' => 'CA',
        // ...
    ]),
]);
```

## Drivers

### Available Drivers

| Driver | Token (`.env`) | HTTPS | Notes |
|---|---|---|---|
| [IpApi](https://ip-api.com) | - | No | Default driver. The free API is for non-commercial use and is rate limited. |
| [IpApiPro](https://pro.ip-api.com) | `IP_API_TOKEN` | Yes | Paid version of IpApi, same fields. |
| [IpData](https://ipdata.co) | `IPDATA_TOKEN` | Yes | Token required. |
| [IpInfo](https://ipinfo.io) | `IPINFO_TOKEN` (optional) | No | Doesn't return the country name. |
| [IpInfo Lite](https://ipinfo.io/lite) | `IPINFO_TOKEN` | Yes | Country only. |
| [Kloudend](https://ipapi.co) | `KLOUDEND_TOKEN` (optional) | Yes | |
| [GeoPlugin](https://www.geoplugin.com) | - | No | The free API has been discontinued, requests without a paid plan return `403`. |
| [MaxMind](https://www.maxmind.com/en/home) | `MAXMIND_LICENSE_KEY` | - | Reads a local database, no request per lookup. The web service also needs `MAXMIND_USER_ID`. |
| [Cloudflare](https://developers.cloudflare.com/network/ip-geolocation/) | - | - | Reads the headers Cloudflare adds to the request, so the app must be behind Cloudflare and the IP passed to `Location::get()` is ignored. |
| [IP2Location.io](https://www.ip2location.io/) | `IP2LOCATIONIO_TOKEN` | Yes | |

#### Available Fields

Every position has `ip` and `driver`. The other fields depend on the driver:

| Field | IpApi / Pro | IpData | IpInfo | IpInfo Lite | Kloudend | GeoPlugin | MaxMind City | MaxMind Country | Cloudflare | IP2Location.io |
|---|---|---|---|---|---|---|---|---|---|---|
| `countryName` | ✓ | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ |
| `countryCode` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `isoCode` | | | | | | | ✓ | ✓ | ✓ | |
| `currencyCode` | ✓ | ✓ | | | ✓ | | | | | ✓ ¹ |
| `regionCode` | ✓ | ✓ | | | ✓ | ✓ | ✓ | | ✓ ² | ✓ ¹ |
| `regionName` | ✓ | ✓ | ✓ | | ✓ | ✓ | ✓ | | ✓ ² | ✓ |
| `cityName` | ✓ | ✓ | ✓ | | ✓ | ✓ | ✓ | | ✓ ² | ✓ |
| `postalCode` | ✓ | ✓ | ✓ | | ✓ | | ✓ | | ✓ ² | ✓ |
| `latitude`, `longitude` | ✓ | ✓ | ✓ | | ✓ | ✓ | ✓ | | ✓ ² | ✓ |
| `timezone` | ✓ | ✓ | ✓ | | ✓ | ✓ | ✓ | | ✓ ² | ✓ ³ |
| `metroCode` | | | | | | | ✓ ⁴ | | | ✓ ¹ |
| `areaCode` | | | | | | ✓ | | | | ✓ ¹ |

1. Only on paid IP2Location.io plans, `null` on the free plan.
2. Only when the "Add visitor location headers" Managed Transform is enabled in Cloudflare. Without it, only the country is available.
3. A UTC offset such as `-07:00`, not a timezone name like the other drivers.
4. Deprecated by MaxMind.

`zipCode` is deprecated. It holds the same value as `postalCode`.

#### Setting up MaxMind with a self-hosted database (optional)

It is encouraged to set up MaxMind as a fallback driver using a local database 
so that some location information is returned in the event of hitting
a rate limit from the external web services.

To set up MaxMind to retrieve the user's location from your own server, you must:

1. Create a [maxmind account](https://www.maxmind.com/en/geolite2/signup) and sign in
2. Click "Manage License Keys" from the profile menu dropdown
3. Generate a new license key
4. Copy the license key and save it into your `.env` file using the `MAXMIND_LICENSE_KEY` key
5. Run `php artisan location:update` to download the latest `.mmdb` file into your `database/maxmind` directory
6. That's it, you're all set!

> **Note**: Keep in mind, you'll need to update this file by running `location:update` 
> on a regular basis to retrieve the most current information from your visitors.

#### Sharing a MaxMind database across servers

When an application runs on multiple servers, the MaxMind database may be stored
on a shared Laravel filesystem disk. Each server caches the shared database on
its local filesystem because the MaxMind reader requires a local file path.

```php
// config/location.php

'maxmind' => [
    // ...

    'storage' => [
        'disk' => env('MAXMIND_STORAGE_DISK'),
        'path' => env('MAXMIND_STORAGE_PATH', 'maxmind/GeoLite2-City.mmdb'),
        'ttl' => env('MAXMIND_STORAGE_TTL', 3600),
    ],

    'local' => [
        'type' => 'city',
        'path' => storage_path('app/maxmind/GeoLite2-City.mmdb'),
        // ...
    ],
],
```

For example, the database may be stored on the application's `s3` disk:

```ini
MAXMIND_STORAGE_DISK=s3
MAXMIND_STORAGE_PATH=maxmind/GeoLite2-City.mmdb
MAXMIND_STORAGE_TTL=3600
```

The `ttl` value is the number of seconds a local database may be used before the
configured disk is checked for a newer version.

Only one server should be scheduled to download and publish the database from
MaxMind. Laravel's `onOneServer` method may be used when every server uses the
same lock-capable cache store:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('location:update')
    ->daily()
    ->onOneServer();
```

Other servers will download the database from the shared disk when it is first
needed and refresh their local copy after its TTL has elapsed.

Each server coordinates refreshes with a lock beside its local database. While
one worker refreshes the database, other workers continue using the existing
copy. If a refresh fails, the existing database is retained and another attempt
is made after the configured TTL. Downloads are validated before replacing the
local copy.

#### Localized MaxMind names

MaxMind returns `countryName`, `regionName` and `cityName` in English by default.
To get them in another language, set the `locales` list. It applies to both the
local database and the web service:

```php
// config/location.php

'maxmind' => [
    'web' => [
        // ...
        'locales' => ['de'],
    ],
],
```

Supported codes: `de`, `en`, `es`, `fr`, `ja`, `pt-BR`, `ru`, `zh-CN` (use
`pt-BR`, not `pt_BR`). English is appended when missing, so an untranslated name
falls back to English instead of `null`. If you cache results, include the
locale in the cache key.

Only the MaxMind driver uses this setting. Other drivers return names in the
language of their API.

### Fallback Drivers

In the config file, you can specify as many fallback drivers as you wish. It is
recommended to configure the MaxMind driver with the local database `.mmdb`
file (mentioned above), so you are alwaysretrieving some generic location
information from the visitor.

If an exception occurs trying to grab a driver (such as a 400/500 error if the
providers API changes), it will automatically use the next driver in line.

### Timeouts

The `http` config option times out a single driver's request. Each driver gets
its own, so with the default configuration an unreachable provider costs you
that timeout five times over before the lookup gives up.

Set `total_timeout` to give the whole lookup one shared budget instead:

```php
'total_timeout' => 5,
```

### Creating your own drivers

To create your own driver, simply create a class in your application, and extend the abstract Driver:

```php
namespace App\Location\Drivers;

use Illuminate\Support\Fluent;
use Illuminate\Support\Facades\Http;
use Stevebauman\Location\Position;
use Stevebauman\Location\Request;
use Stevebauman\Location\Drivers\Driver;

class MyDriver extends Driver
{    
    protected function process(Request $request): Fluent
    {
         $response = Http::get("https://driver-url.com", ['ip' => $request->getIp()]);
         
         return new Fluent($response->json());
    }

    protected function hydrate(Position $position, Fluent $location): Position
    {
        $position->countryCode = $location->country_code;

        return $position;
    }
}
```

Then, insert your driver class name into the configuration file:

```php
// config/location.php

'driver' => App\Location\Drivers\MyDriver::class,
```

## Versioning

Location is versioned under the Semantic Versioning guidelines as much as possible.

Releases will be numbered with the following format:

```
<major>.<minor>.<patch>
```

And constructed with the following guidelines:

- Breaking backward compatibility bumps the major and resets the minor and patch.
- New additions without breaking backward compatibility bumps the minor and resets the patch.
- Bug fixes and misc changes bumps the patch.

Minor versions are not maintained individually, and you're encouraged to upgrade through to the next minor version.

Major versions are maintained individually through separate branches.
