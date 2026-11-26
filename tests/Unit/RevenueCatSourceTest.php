<?php

namespace Tests\Unit;

use App\Growth\AppDefinition;
use App\Growth\Metric;
use App\Growth\SourceError;
use App\Growth\Sources\RevenueCat\RevenueCatSource;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RevenueCatSourceTest extends TestCase
{
    private AppDefinition $notes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notes = new AppDefinition('notes', 'Lumen Notes', 'proj1a2b', 'sk_secret_value');
    }

    private function source(): RevenueCatSource
    {
        return new RevenueCatSource($this->app->make(Factory::class), 'USD');
    }

    public function test_picks_the_measure_by_name_and_sums_by_utc_day(): void
    {
        $series = RevenueCatSource::parse([
            'measures' => [['display_name' => 'Transactions'], ['display_name' => 'Revenue']],
            'values' => [
                ['cohort' => 1767225600, 'measure' => 0, 'value' => 99],          // other measure
                ['cohort' => 1767225600, 'measure' => 1, 'value' => 10.5],        // 2026-01-01, seconds
                ['cohort' => 1767312000000, 'measure' => 1, 'value' => 20],       // 2026-01-02, milliseconds
                ['cohort' => 1767312000000, 'measure' => 1, 'value' => 5, 'segment' => 2], // a segment: not the total
                ['cohort' => 1767398400, 'measure' => 1, 'value' => 3, 'incomplete' => true],
                'garbage',
            ],
        ], 'Revenue');

        $this->assertSame(['2026-01-01' => 10.5, '2026-01-02' => 20.0, '2026-01-03' => 3.0], $series->values);
        $this->assertSame(['2026-01-03'], $series->incomplete);
    }

    public function test_falls_back_to_the_first_measure_if_the_name_changes(): void
    {
        $series = RevenueCatSource::parse([
            'measures' => [['display_name' => 'Renamed']],
            'values' => [['cohort' => 1767225600, 'measure' => 0, 'value' => 7]],
        ], 'Revenue');

        $this->assertSame(['2026-01-01' => 7.0], $series->values);
    }

    public function test_requests_the_right_chart_with_the_key_and_selectors(): void
    {
        Http::fake(['api.revenuecat.com/*' => Http::response(['measures' => [], 'values' => []])]);

        $this->source()->daily($this->notes, Metric::Proceeds, '2026-01-01', '2026-01-31');

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://api.revenuecat.com/v2/projects/proj1a2b/charts/revenue?')
                && $request->hasHeader('Authorization', 'Bearer sk_secret_value')
                && $query['selectors'] === '{"revenue_type":"proceeds"}'
                && $query['currency'] === 'USD'
                && $query['start_date'] === '2026-01-01' && $query['end_date'] === '2026-01-31';
        });
    }

    public function test_counts_are_not_requested_in_a_currency(): void
    {
        Http::fake(['*' => Http::response(['values' => []])]);

        $this->source()->daily($this->notes, Metric::Customers, '2026-01-01', '2026-01-02');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/charts/customers_new?') && ! str_contains($r->url(), 'currency='));
    }

    /** @return array<string, array{int, string}> */
    public static function failures(): array
    {
        return [
            'bad key' => [401, 'v2 secret key'],
            'no permission' => [403, 'permission'],
            'wrong project' => [404, 'project id'],
            'rate limited' => [429, 'Try again'],
            'server error' => [502, 'answered 502'],
        ];
    }

    #[DataProvider('failures')]
    public function test_failures_become_readable_errors_without_secrets(int $status, string $hint): void
    {
        Http::fake(['*' => Http::response(['message' => 'detail'], $status)]);

        try {
            $this->source()->daily($this->notes, Metric::Revenue, '2026-01-01', '2026-01-02');
            $this->fail('expected a SourceError');
        } catch (SourceError $e) {
            $this->assertStringContainsString('Lumen Notes', $e->getMessage());
            $this->assertStringContainsString($hint, $e->getMessage());
            $this->assertStringNotContainsString('sk_secret_value', $e->getMessage());
            $this->assertStringNotContainsString('revenuecat.com', $e->getMessage());
        }
    }

    public function test_an_app_without_keys_fails_without_calling_out(): void
    {
        Http::fake();

        $this->expectException(SourceError::class);
        try {
            $this->source()->daily(new AppDefinition('x', 'X'), Metric::Revenue, '2026-01-01', '2026-01-02');
        } finally {
            Http::assertNothingSent();
        }
    }
}
