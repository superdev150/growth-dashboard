<?php

declare(strict_types=1);

namespace App\Growth\Fx;

use App\Growth\SourceError;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Throwable;

/**
 * Converts between USD and another currency with a free daily-rates service.
 *
 * A rate is cached for hours, and the last good one is kept forever: if the service is down, the dashboard keeps
 * working with yesterday's rate (and says so) rather than failing. Only a currency the service has never answered
 * for falls back to the configured rate.
 */
final class ExchangeRates
{
    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly string $url,
        private readonly int $cacheHours = 12,
        private readonly ?float $fallbackPerUsd = null,
    ) {}

    public function perUsd(string $currency): Rate
    {
        $currency = strtoupper($currency);
        if ($currency === 'USD') {
            return new Rate(1.0, 'identity');
        }

        $lastGood = "fx:last:{$currency}";

        // Arrays, not objects, go into the cache: Laravel can refuse to unserialize classes it was not told about.
        $rate = $this->cache->remember("fx:{$currency}", now()->addHours($this->cacheHours), function () use ($currency, $lastGood) {
            try {
                $data = $this->http->acceptJson()->timeout(10)->get($this->url)->throw()->json();
                $value = (float) ($data['rates'][$currency] ?? 0);
                if ($value > 0) {
                    $rate = ['per_usd' => $value, 'source' => (string) parse_url($this->url, PHP_URL_HOST), 'date' => gmdate('Y-m-d', (int) ($data['time_last_update_unix'] ?? time()))];
                    $this->cache->forever($lastGood, $rate);

                    return $rate;
                }
            } catch (Throwable) {
                // fall through to the last good rate
            }

            if ($last = $this->cache->get($lastGood)) {
                return ['source' => $last['source'].' (last known)'] + $last;
            }
            if ($this->fallbackPerUsd !== null && $this->fallbackPerUsd > 0) {
                return ['per_usd' => $this->fallbackPerUsd, 'source' => 'configured fallback', 'date' => ''];
            }

            throw new SourceError("No exchange rate for {$currency}: the rates service is down and no fallback is configured.");
        });

        return new Rate((float) $rate['per_usd'], $rate['source'], $rate['date']);
    }
}
