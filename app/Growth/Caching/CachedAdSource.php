<?php

declare(strict_types=1);

namespace App\Growth\Caching;

use App\Growth\AdReport;
use App\Growth\Contracts\AdSource;
use Illuminate\Contracts\Cache\Repository as Cache;

/** Same contract as CachedRevenueSource, for the ad account. */
final class CachedAdSource implements AdSource
{
    public function __construct(
        private readonly AdSource $inner,
        private readonly Cache $cache,
        private readonly int $minutes,
        private readonly bool $fresh = false,
    ) {}

    public function report(string $from, string $to): AdReport
    {
        $key = "growth:ads:{$from}:{$to}";
        if ($this->fresh) {
            $this->cache->forget($key);
        }

        return AdReport::fromArray($this->cache->remember(
            $key,
            now()->addMinutes($this->minutes),
            fn () => $this->inner->report($from, $to)->toArray(),
        ));
    }
}
