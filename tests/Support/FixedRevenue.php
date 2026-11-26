<?php

namespace Tests\Support;

use App\Growth\AppDefinition;
use App\Growth\Contracts\RevenueSource;
use App\Growth\DailySeries;
use App\Growth\Metric;
use App\Growth\SourceError;

/** Revenue that is the same every day: `$perDay[app][metric]`. Apps listed in `$failing` throw. */
final class FixedRevenue implements RevenueSource
{
    public int $calls = 0;

    /**
     * @param  array<string, array<string, float>>  $perDay
     * @param  list<string>  $failing
     */
    public function __construct(private array $perDay, private array $failing = []) {}

    public function daily(AppDefinition $app, Metric $metric, string $from, string $to): DailySeries
    {
        $this->calls++;
        if (in_array($app->id, $this->failing, true)) {
            throw new SourceError("{$app->name}: key revoked");
        }
        $values = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
            $values[gmdate('Y-m-d', $d)] = $this->perDay[$app->id][$metric->value] ?? 0.0;
        }

        return new DailySeries($values);
    }
}
