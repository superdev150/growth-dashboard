<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrowthApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-03-10 15:00:00');
        $this->travelTo(CarbonImmutable::now());
    }

    private function live(): void
    {
        config([
            'growth.driver' => 'live',
            'growth.apps.notes.revenuecat' => ['project' => 'p_notes', 'key' => 'sk_notes'],
            'growth.apps.trails.revenuecat' => ['project' => 'p_trails', 'key' => 'sk_trails'],
            'growth.meta' => ['token' => 'EAAtoken', 'account' => 'act_1', 'version' => 'v21.0'],
        ]);
    }

    private function fakeUpstream(): void
    {
        $chart = ['measures' => [['display_name' => 'Revenue']], 'values' => [
            ['cohort' => CarbonImmutable::parse('2026-03-09', 'UTC')->timestamp, 'measure' => 0, 'value' => 12.5],
        ]];
        Http::fake([
            'api.revenuecat.com/v2/projects/p_notes/*' => Http::response($chart),
            'api.revenuecat.com/v2/projects/p_trails/*' => Http::response(['message' => 'Invalid key'], 401),
            'graph.facebook.com/*/act_1?*' => Http::response(['currency' => 'USD']),
            'graph.facebook.com/*/campaigns*' => Http::response(['data' => []]),
            'graph.facebook.com/*/insights*' => Http::response(['data' => [
                ['date_start' => '2026-03-09', 'campaign_id' => '9', 'campaign_name' => 'LN search', 'spend' => '20', 'actions' => [['action_type' => 'mobile_app_install', 'value' => '8']]],
            ]]),
        ]);
    }

    public function test_demo_mode_needs_no_keys_and_is_deterministic(): void
    {
        Http::fake();

        $first = $this->getJson('/api/growth?days=30')->assertOk()->json();
        $this->assertSame('demo', $first['driver']);
        $this->assertCount(30, $first['dates']);
        $this->assertSame(['notes', 'trails'], array_column($first['apps'], 'id'));
        $this->assertGreaterThan(0, $first['totals']['revenue']['value']);
        $this->assertGreaterThan(0, $first['ads']['unassigned']['spend']);
        $this->assertSame(['2026-03-10'], $first['apps'][0]['incomplete']);

        $again = $this->getJson('/api/growth?days=30&refresh=1')->json();
        $this->assertSame($first['totals'], $again['totals']);
        Http::assertNothingSent();
    }

    public function test_defaults_to_thirty_days(): void
    {
        $this->getJson('/api/growth')->assertOk()->assertJsonPath('days', 30);
    }

    public function test_rejects_unsupported_periods(): void
    {
        $this->get('/api/growth?days=14')->assertStatus(422)->assertJsonValidationErrors('days');
        $this->get('/api/growth?days=abc')->assertStatus(422);
    }

    public function test_live_mode_isolates_a_failing_app(): void
    {
        $this->live();
        $this->fakeUpstream();

        $response = $this->getJson('/api/growth?days=7')->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $response->assertJsonPath('driver', 'live')
            ->assertJsonPath('partial', true)
            ->assertJsonPath('apps.0.error', null)
            ->assertJsonPath('apps.0.series.revenue.5', 12.5)
            ->assertJsonPath('apps.0.series.spend.5', 20)
            ->assertJsonPath('apps.0.series.installs.5', 8)
            ->assertJsonPath('apps.1.error', 'Pocket Trails: RevenueCat rejected the key (401). It must be a v2 secret key.');
        $this->assertStringNotContainsString('sk_', $response->getContent());
        $this->assertStringNotContainsString('EAAtoken', $response->getContent());
    }

    public function test_upstream_answers_are_cached_until_refresh(): void
    {
        $this->live();
        $this->fakeUpstream();

        $sent = fn (string $needle) => Http::recorded(fn (Request $request) => str_contains($request->url(), $needle))->count();

        $this->getJson('/api/growth?days=7')->assertOk();
        $this->assertSame([4, 3, 1], [$sent('p_notes'), $sent('graph.facebook.com'), $sent('p_trails')]);

        // Second load: healthy answers come from the cache; the failure was not cached, so that app is retried.
        $this->getJson('/api/growth?days=7')->assertOk();
        $this->assertSame([4, 3, 2], [$sent('p_notes'), $sent('graph.facebook.com'), $sent('p_trails')]);

        // Refresh goes upstream for everything.
        $this->getJson('/api/growth?days=7&refresh=1')->assertOk();
        $this->assertSame([8, 6, 3], [$sent('p_notes'), $sent('graph.facebook.com'), $sent('p_trails')]);
    }

    public function test_refresh_is_rate_limited(): void
    {
        foreach (range(1, 4) as $_) {
            $this->getJson('/api/growth?days=7&refresh=1')->assertOk();
        }
        $this->getJson('/api/growth?days=7&refresh=1')->assertStatus(429);
        $this->getJson('/api/growth?days=7')->assertOk();
    }

    public function test_the_dashboard_shell_loads_the_react_app(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk()->assertSee('<div id="root"></div>', false);
    }
}
