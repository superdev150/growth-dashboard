<?php

namespace Tests\Unit;

use App\Growth\AdRow;
use App\Growth\AppDefinition;
use App\Growth\Contracts\AdSource;
use App\Growth\Fx\ExchangeRates;
use App\Growth\GrowthReport;
use App\Growth\Period;
use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Psr\Log\NullLogger;
use Tests\Support\FixedAds;
use Tests\Support\FixedRevenue;
use Tests\TestCase;

class GrowthReportTest extends TestCase
{
    private const TODAY = '2026-03-10';

    private Period $period;

    /** @var array<string, AppDefinition> */
    private array $apps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->period = Period::lastDays(7, CarbonImmutable::parse(self::TODAY, 'UTC'));
        $this->apps = [
            'notes' => new AppDefinition('notes', 'Notes', stores: ['id111']),
            'trails' => new AppDefinition('trails', 'Trails', prefixes: ['PT ']),
        ];
    }

    /** One row per day of both periods (14 days). */
    private function dailyRows(string $id, string $name, float $spend, int $installs, string $store = ''): array
    {
        $rows = [];
        foreach (range(0, 13) as $i) {
            $date = CarbonImmutable::parse(self::TODAY, 'UTC')->subDays($i)->toDateString();
            $rows[] = new AdRow($date, $id, $name, $spend, 1000, 50, $installs, 'ACTIVE', '', $store);
        }

        return $rows;
    }

    private function report(FixedRevenue $revenue, ?AdSource $ads, string $currency = 'USD'): array
    {
        $rates = new ExchangeRates($this->app->make(Factory::class), new Repository(new ArrayStore), 'https://rates.test/usd', 12);

        return (new GrowthReport($this->apps, $revenue, $ads, $rates, $currency, new NullLogger))->build($this->period);
    }

    private function revenue(array $failing = []): FixedRevenue
    {
        return new FixedRevenue([
            'notes' => ['revenue' => 100.0, 'proceeds' => 70.0, 'customers' => 10.0, 'trials' => 4.0],
            'trails' => ['revenue' => 50.0, 'proceeds' => 35.0, 'customers' => 5.0, 'trials' => 2.0],
        ], $failing);
    }

    private function ads(): FixedAds
    {
        return new FixedAds([
            ...$this->dailyRows('c1', 'Install campaign', 40.0, 20, 'https://apps.apple.com/app/id111'),
            ...$this->dailyRows('c2', 'PT reels', 10.0, 10),
            ...$this->dailyRows('c3', 'Brand awareness', 5.0, 0),
        ]);
    }

    public function test_series_follow_the_dates_and_the_previous_period_is_one_total(): void
    {
        $report = $this->report($this->revenue(), $this->ads());

        $this->assertCount(7, $report['dates']);
        $this->assertSame(self::TODAY, end($report['dates']));
        $notes = $report['apps'][0];
        $this->assertSame(array_fill(0, 7, 100.0), $notes['series']['revenue']);
        $this->assertSame(array_fill(0, 7, 40.0), $notes['series']['spend']);
        $this->assertSame(700.0, $notes['previous']['revenue']);
        $this->assertSame(140, $notes['previous']['installs']);
        $this->assertFalse($report['partial']);
    }

    public function test_totals_and_ratios_are_computed_from_sums(): void
    {
        $totals = $this->report($this->revenue(), $this->ads())['totals'];

        $this->assertSame(350.0, $totals['spend']['value']);     // (40 + 10) × 7; brand spend is nobody's
        $this->assertSame(210.0, $totals['installs']['value']);  // (20 + 10) × 7
        $this->assertSame(1.67, $totals['cpi']['value']);        // 350 / 210, not the mean of 2.00 and 1.00
        $this->assertSame(3.0, $totals['roas']['value']);        // 1050 / 350
        $this->assertSame(0.0, $totals['revenue']['change']);    // flat
    }

    public function test_unattributed_campaigns_are_reported_apart(): void
    {
        $ads = $this->report($this->revenue(), $this->ads())['ads'];

        $this->assertSame(['spend' => 35.0, 'campaigns' => 1], $ads['unassigned']);
        $this->assertSame(['c1', 'c2'], array_column($ads['campaigns'], 'id'));   // by spend
        $this->assertSame(2.0, $ads['campaigns'][0]['cpi']);
    }

    public function test_one_failing_app_does_not_take_down_the_rest(): void
    {
        $report = $this->report($this->revenue(failing: ['trails']), $this->ads());

        $this->assertTrue($report['partial']);
        $this->assertSame('Trails: key revoked', $report['apps'][1]['error']);
        $this->assertNull($report['apps'][0]['error']);
        $this->assertSame(700.0, $report['totals']['revenue']['value']);
        // Its ad spend is still shown…
        $this->assertSame(array_fill(0, 7, 10.0), $report['apps'][1]['series']['spend']);
        // …but left out of ROAS, which would otherwise count spend without its revenue: 700 / 280.
        $this->assertSame(2.5, $report['totals']['roas']['value']);
    }

    public function test_a_failing_ad_account_leaves_revenue_intact(): void
    {
        $report = $this->report($this->revenue(), new FixedAds(null));

        $this->assertSame('Meta answered 500.', $report['ads']['error']);
        $this->assertTrue($report['partial']);
        $this->assertSame(1050.0, $report['totals']['revenue']['value']);
        $this->assertNull($report['totals']['cpi']['value']);   // no installs: no ratio, not a division by zero
        $this->assertNull($report['totals']['roas']['value']);
    }

    public function test_no_ad_account_configured(): void
    {
        $report = $this->report($this->revenue(), null);

        $this->assertSame('The ad account is not configured.', $report['ads']['error']);
    }

    public function test_spend_is_converted_from_the_account_currency(): void
    {
        Http::fake(['rates.test/*' => Http::response(['rates' => ['HNL' => 25.0]])]);
        $rows = $this->dailyRows('c1', 'Install campaign', 1000.0, 20, 'id111');

        $report = $this->report($this->revenue(), new FixedAds($rows, 'HNL'));

        $this->assertSame(array_fill(0, 7, 40.0), $report['apps'][0]['series']['spend']);
        $this->assertSame('HNL', $report['ads']['account_currency']);
        $this->assertSame(25.0, $report['ads']['rate']['per_usd']);
    }

    public function test_change_is_null_when_there_was_nothing_before(): void
    {
        $rows = array_filter($this->dailyRows('c1', 'New', 10.0, 5, 'id111'), fn (AdRow $r) => $this->period->isCurrent($r->date));

        $totals = $this->report($this->revenue(), new FixedAds(array_values($rows)))['totals'];

        $this->assertSame(0.0, $totals['spend']['previous']);
        $this->assertNull($totals['spend']['change']);
    }
}
