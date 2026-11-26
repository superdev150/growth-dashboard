<?php

declare(strict_types=1);

namespace App\Growth\Caching;

use App\Growth\AppDefinition;
use App\Growth\Contracts\RevenueSource;
use App\Growth\DailySeries;
use App\Growth\Metric;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Caches each (app, metric, range) answer. `$fresh` (the dashboard's Refresh button) skips the cached copy and
 * stores the new one. Failures are not cached: the next load tries again.
 */
final class CachedRevenueSource implements RevenueSource
{
    public function __construct(
        private readonly RevenueSource $inner,
        private readonly Cache $cache,
        private readonly int $minutes,
        private readonly bool $fresh = false,
    ) {}

    public function daily(AppDefinition $app, Metric $metric, string $from, string $to): DailySeries
    {
        $key = "growth:revenue:{$app->id}:{$metric->value}:{$from}:{$to}";
        if ($this->fresh) {
            $this->cache->forget($key);
        }

        return DailySeries::fromArray($this->cache->remember(
            $key,
            now()->addMinutes($this->minutes),
            fn () => $this->inner->daily($app, $metric, $from, $to)->toArray(),
        ));
    }
}
