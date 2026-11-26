<?php

namespace App\Providers;

use App\Growth\GrowthReportFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GrowthReportFactory::class, fn ($app) => new GrowthReportFactory(
            config('growth'),
            $app->make(Http::class),
            $app->make('cache.store'),
            $app->make('log'),
        ));
    }

    public function boot(): void
    {
        // Shortest round-trip floats in JSON (1052.38, not 1052.380000000000109). PHP's default, but some
        // distributions ship php.ini with 17.
        ini_set('serialize_precision', '-1');

        // Loading the dashboard is cheap (cached). Refresh goes upstream, whose limits are tight: allow a few a minute.
        RateLimiter::for('growth', fn (Request $request) => $request->boolean('refresh')
            ? Limit::perMinute(4)->by('refresh:'.$request->ip())
            : Limit::perMinute(60)->by($request->ip()));
    }
}
