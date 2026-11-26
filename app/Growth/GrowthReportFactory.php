<?php

declare(strict_types=1);

namespace App\Growth;

use App\Growth\Caching\CachedAdSource;
use App\Growth\Caching\CachedRevenueSource;
use App\Growth\Fx\ExchangeRates;
use App\Growth\Sources\Demo\DemoAdSource;
use App\Growth\Sources\Demo\DemoRevenueSource;
use App\Growth\Sources\Meta\MetaAdsSource;
use App\Growth\Sources\RevenueCat\RevenueCatSource;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Psr\Log\LoggerInterface;

/** Wires the report for one request: demo or live sources, behind the cache, honouring Refresh. */
final class GrowthReportFactory
{
    /** @param  array<string, mixed>  $config  config('growth') */
    public function __construct(
        private readonly array $config,
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly LoggerInterface $log,
    ) {}

    public function make(bool $fresh = false): GrowthReport
    {
        $live = ($this->config['driver'] ?? 'demo') === 'live';
        $currency = strtoupper((string) ($this->config['currency'] ?? 'USD'));
        $minutes = (int) ($this->config['cache_minutes'] ?? 10);

        $revenue = $live ? new RevenueCatSource($this->http, $currency) : new DemoRevenueSource;
        $ads = $live ? $this->metaOrNull() : new DemoAdSource;

        $fx = $this->config['fx'] ?? [];

        return new GrowthReport(
            apps: AppDefinition::allFromConfig($this->config['apps'] ?? []),
            revenue: new CachedRevenueSource($revenue, $this->cache, $minutes, $fresh),
            ads: $ads ? new CachedAdSource($ads, $this->cache, $minutes, $fresh) : null,
            rates: new ExchangeRates(
                $this->http,
                $this->cache,
                (string) ($fx['url'] ?? 'https://open.er-api.com/v6/latest/USD'),
                (int) ($fx['cache_hours'] ?? 12),
                isset($fx['fallback_rate']) && $fx['fallback_rate'] !== '' ? (float) $fx['fallback_rate'] : null,
            ),
            currency: $currency,
            log: $this->log,
            driver: $live ? 'live' : 'demo',
        );
    }

    private function metaOrNull(): ?MetaAdsSource
    {
        $meta = $this->config['meta'] ?? [];
        if (empty($meta['token']) || empty($meta['account'])) {
            return null;
        }

        return new MetaAdsSource($this->http, (string) $meta['token'], (string) $meta['account'], (string) ($meta['version'] ?? 'v21.0'));
    }
}
