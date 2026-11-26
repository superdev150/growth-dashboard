<?php

namespace Tests\Unit;

use App\Growth\SourceError;
use App\Growth\Sources\Meta\MetaAdsSource;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaAdsSourceTest extends TestCase
{
    private function source(): MetaAdsSource
    {
        return new MetaAdsSource($this->app->make(Factory::class), 'EAAtoken123', 'act_42');
    }

    public function test_installs_prefer_the_most_complete_action(): void
    {
        $row = ['actions' => [
            ['action_type' => 'link_click', 'value' => '90'],
            ['action_type' => 'app_install', 'value' => '7'],
            ['action_type' => 'omni_app_install', 'value' => '9'],
        ]];

        $this->assertSame(9, MetaAdsSource::installs($row));
        $this->assertSame(7, MetaAdsSource::installs(['actions' => [['action_type' => 'app_install', 'value' => '7']]]));
        $this->assertSame(0, MetaAdsSource::installs([]));
    }

    public function test_joins_campaigns_into_daily_insights_across_pages(): void
    {
        Http::fake([
            'graph.facebook.com/v21.0/act_42?*' => Http::response(['currency' => 'hnl']),
            'graph.facebook.com/v21.0/act_42/campaigns*' => Http::response(['data' => [
                ['id' => 'c1', 'effective_status' => 'ACTIVE', 'promoted_object' => ['object_store_url' => 'https://apps.apple.com/app/id111']],
            ]]),
            'graph.facebook.com/v21.0/act_42/insights*' => Http::sequence()
                ->push(['data' => [['date_start' => '2026-01-01', 'campaign_id' => 'c1', 'campaign_name' => 'A', 'spend' => '12.50', 'impressions' => '1000', 'inline_link_clicks' => '30']],
                    'paging' => ['cursors' => ['after' => 'NEXT'], 'next' => 'https://graph.facebook.com/…']])
                ->push(['data' => [['date_start' => '2026-01-02', 'campaign_id' => 'gone', 'campaign_name' => 'Deleted', 'spend' => '3']],
                    'paging' => ['cursors' => ['after' => 'END']]]),
        ]);

        $report = $this->source()->report('2026-01-01', '2026-01-02');

        $this->assertSame('HNL', $report->currency);
        $this->assertCount(2, $report->rows);
        $this->assertSame(12.5, $report->rows[0]->spend);
        $this->assertSame('https://apps.apple.com/app/id111', $report->rows[0]->storeUrl);
        $this->assertSame('ACTIVE', $report->rows[0]->status);
        // A campaign deleted since it spent still counts, as archived and unattributed by promotion.
        $this->assertSame('ARCHIVED', $report->rows[1]->status);
        $this->assertSame('', $report->rows[1]->storeUrl);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/insights') && str_contains($r->url(), 'after=NEXT'));
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer EAAtoken123'));
    }

    public function test_pagination_is_capped(): void
    {
        Http::fake([
            '*/act_42?*' => Http::response(['currency' => 'USD']),
            '*/campaigns*' => Http::response(['data' => []]),
            // A broken cursor that never ends.
            '*/insights*' => Http::response(['data' => [], 'paging' => ['cursors' => ['after' => 'SAME'], 'next' => 'x']]),
        ]);

        $this->source()->report('2026-01-01', '2026-01-02');

        Http::assertSentCount(2 + MetaAdsSource::MAX_PAGES);
    }

    public function test_errors_carry_metas_message_but_never_the_token(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 400)]);

        try {
            $this->source()->report('2026-01-01', '2026-01-02');
            $this->fail('expected a SourceError');
        } catch (SourceError $e) {
            $this->assertSame('Meta answered 400: Invalid OAuth access token.', $e->getMessage());
        }
    }
}
