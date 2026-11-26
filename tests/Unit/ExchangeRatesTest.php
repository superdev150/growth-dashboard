<?php

namespace Tests\Unit;

use App\Growth\Fx\ExchangeRates;
use App\Growth\SourceError;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRatesTest extends TestCase
{
    private const URL = 'https://open.er-api.com/v6/latest/USD';

    private Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new Repository(new ArrayStore);
    }

    private function rates(?float $fallback = null): ExchangeRates
    {
        return new ExchangeRates($this->app->make(Factory::class), $this->cache, self::URL, 12, $fallback);
    }

    public function test_usd_needs_no_lookup(): void
    {
        Http::fake();

        $this->assertSame(1.0, $this->rates()->perUsd('usd')->perUsd);
        Http::assertNothingSent();
    }

    public function test_fetches_once_then_serves_from_cache(): void
    {
        Http::fake([self::URL => Http::response(['rates' => ['HNL' => 26.1], 'time_last_update_unix' => 1767225600])]);

        $rate = $this->rates()->perUsd('HNL');
        $this->rates()->perUsd('hnl');

        $this->assertSame(26.1, $rate->perUsd);
        $this->assertSame('open.er-api.com', $rate->source);
        $this->assertSame('2026-01-01', $rate->date);
        Http::assertSentCount(1);
    }

    public function test_keeps_the_last_good_rate_when_the_service_goes_down(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(['rates' => ['HNL' => 26.1], 'time_last_update_unix' => 1767225600])
            ->push('down', 503)]);

        $this->rates()->perUsd('HNL');
        $this->travel(13)->hours(); // the short-lived copy expires
        $rate = $this->rates()->perUsd('HNL');

        $this->assertSame(26.1, $rate->perUsd);
        $this->assertStringContainsString('last known', $rate->source);
    }

    public function test_configured_fallback_when_it_never_answered(): void
    {
        Http::fake([self::URL => Http::response('down', 503)]);

        $this->assertSame(25.0, $this->rates(25.0)->perUsd('HNL')->perUsd);
    }

    public function test_an_unknown_currency_without_fallback_fails_clearly(): void
    {
        Http::fake([self::URL => Http::response(['rates' => ['EUR' => 0.9]])]);

        $this->expectException(SourceError::class);
        $this->expectExceptionMessage('No exchange rate for XYZ');
        $this->rates()->perUsd('XYZ');
    }
}
