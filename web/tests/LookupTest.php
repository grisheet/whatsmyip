<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

class LookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['location.ip2locationio.token' => 'test-only-token']);

        Http::preventStrayRequests();
    }

    public function test_empty_home_shows_the_visitor_address_without_a_lookup(): void
    {
        Http::fake();
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.4.4'])
            ->get('/')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('8.8.4.4')
            ->assertSee('data-state="empty"', false)
            ->assertSee('Not looked up yet')
            ->assertSee('name="_token"', false);

        Http::assertNothingSent();
    }

    public function test_legal_pages_and_health_check_render(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('whatsmyip_session')
            ->assertSee('contact@example.org');
        $this->get('/terms')->assertOk()->assertSee('IP2Location.io');
        $this->get('/up')->assertOk();
    }

    public function test_plain_html_lookup_renders_and_caches_provider_fields(): void
    {
        Http::fake(['api.ip2location.io/*' => Http::response([
            'country_name' => 'United Kingdom',
            'region_name' => 'England',
            'city_name' => 'London',
            'zip_code' => 'EC1A',
            'time_zone' => 'Europe/London',
            'latitude' => 51.5,
            'longitude' => -0.12,
        ])]);

        for ($i = 0; $i < 2; $i++) {
            $this->post('/', ['ip' => '8.8.8.8'])
                ->assertOk()
                ->assertSee('data-state="success"', false)
                ->assertSee('United Kingdom')
                ->assertSee('England')
                ->assertSee('London')
                ->assertSee('EC1A')
                ->assertSee('Europe/London')
                ->assertSee('51.5')
                ->assertSee('-0.12');
        }

        Http::assertSentCount(1);
        $this->assertProviderIp('8.8.8.8');
    }

    public function test_ipv6_fragment_uses_canonical_ip_and_handles_missing_fields(): void
    {
        Http::fake(['api.ip2location.io/*' => Http::response([
            'country_code' => 'US',
            'zip_code' => '-',
            'latitude' => 0,
        ])]);

        $this->post('/', ['ip' => '2606:4700:4700:0000:0000:0000:0000:1111'], ['X-Lookup-Fragment' => '1'])
            ->assertOk()
            ->assertDontSee('<!doctype html>', false)
            ->assertSee('2606:4700:4700::1111')
            ->assertSee('Unavailable')
            ->assertSee('>0</dd>', false);

        $this->assertProviderIp('2606:4700:4700::1111');
    }

    public function test_use_my_ip_ignores_the_typed_ip_and_spoofed_forwarding_headers(): void
    {
        Http::fake(['api.ip2location.io/*' => Http::response(['country_name' => 'Canada'])]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.4.4'])
            ->post('/', ['source' => 'visitor', 'ip' => '1.1.1.1'], ['X-Forwarded-For' => '9.9.9.9'])
            ->assertOk()
            ->assertSee('8.8.4.4');

        $this->assertProviderIp('8.8.4.4');
    }

    public function test_use_my_ip_takes_the_nearest_untrusted_address_from_a_trusted_proxy(): void
    {
        config(['site.trusted_proxies' => ['10.20.30.0/24']]);
        Http::fake(['api.ip2location.io/*' => Http::response(['country_name' => 'Canada'])]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.20'])
            ->post('/', ['source' => 'visitor'], ['X-Forwarded-For' => '9.9.9.9, 8.8.4.4'])
            ->assertOk();

        $this->assertProviderIp('8.8.4.4');
    }

    public function test_invalid_input_renders_a_validation_alert(): void
    {
        Http::fake();
        $this->post('/', ['ip' => '<script>'])
            ->assertUnprocessable()
            ->assertSee('Enter a valid IPv4 or IPv6 address.')
            ->assertSee('role="alert"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertDontSee('<script>', false);

        Http::assertNothingSent();
    }

    public static function reservedAddresses(): array
    {
        return array_map(static fn ($address) => [$address], [
            '0.0.0.0', '10.0.0.1', '100.64.0.1', '127.0.0.1', '169.254.0.1',
            '192.0.2.10', '192.168.0.1', '198.18.0.1', '224.1.0.1', '255.255.255.255',
            '::1', '::ffff:8.8.8.8', 'fc00::1', 'fe80::1', 'ff02::1', '2001:db8::1', '3fff::1',
        ]);
    }

    #[DataProvider('reservedAddresses')]
    public function test_non_public_addresses_are_rejected_without_a_provider_call(string $address): void
    {
        Http::fake();
        $this->post('/', ['ip' => $address])
            ->assertUnprocessable()
            ->assertSee('Private and reserved addresses have no public location.');

        Http::assertNothingSent();
    }

    public function test_provider_error_renders_a_retryable_alert_and_is_not_cached(): void
    {
        Http::fake(['api.ip2location.io/*' => Http::sequence()
            ->push(['error' => 'Upstream details'], 503)
            ->push(['country_name' => 'Canada'])]);

        $this->post('/', ['ip' => '1.1.1.1'], ['X-Lookup-Fragment' => '1'])
            ->assertStatus(502)
            ->assertSee('data-state="error"', false)
            ->assertSee('The location provider did not return a usable result.')
            ->assertDontSee('Upstream details');

        $this->post('/', ['ip' => '1.1.1.1'])->assertOk()->assertSee('Canada');
        Http::assertSentCount(2);
    }

    public function test_missing_key_never_uses_the_keyless_provider(): void
    {
        config(['location.ip2locationio.token' => null]);
        Http::fake();

        $this->post('/', ['ip' => '8.8.8.8'])
            ->assertServiceUnavailable()
            ->assertSee('The location provider is currently unavailable.');

        Http::assertNothingSent();
    }

    public function test_visitor_limit_returns_an_alert_and_retry_after(): void
    {
        config(['site.requests_per_minute' => 1]);
        Http::fake(['api.ip2location.io/*' => Http::response(['country_name' => 'Canada'])]);

        $this->post('/', ['ip' => '8.8.8.8'])->assertOk();
        $this->post('/', ['ip' => '8.8.4.4'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertSee('Too many lookup requests.');

        Http::assertSentCount(1);
    }

    public function test_provider_budget_stops_new_requests_but_allows_cache_hits(): void
    {
        config(['site.provider_requests_per_day' => 1]);
        Http::fake(['api.ip2location.io/*' => Http::response(['country_name' => 'Canada'])]);

        $this->post('/', ['ip' => '8.8.8.8'])->assertOk();
        $this->post('/', ['ip' => '8.8.4.4'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertSee('The site has reached its daily lookup limit.');
        $this->post('/', ['ip' => '8.8.8.8'])->assertOk();

        Http::assertSentCount(1);
    }

    private function assertProviderIp(string $expected): void
    {
        Http::assertSent(static function (Request $request) use ($expected): bool {
            $url = parse_url($request->url());
            parse_str($url['query'] ?? '', $query);

            return $url['scheme'] === 'https'
                && $url['host'] === 'api.ip2location.io'
                && $query['ip'] === $expected
                && $query['key'] === 'test-only-token';
        });
    }
}
