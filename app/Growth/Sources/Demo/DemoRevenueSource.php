<?php

declare(strict_types=1);

namespace App\Growth\Sources\Demo;

use App\Growth\AppDefinition;
use App\Growth\Contracts\RevenueSource;
use App\Growth\DailySeries;
use App\Growth\Metric;
use Carbon\CarbonImmutable;

/**
 * Plausible subscription numbers for any app, with no keys. Today is "incomplete", like in RevenueCat, and carries
 * only part of a day.
 */
final class DemoRevenueSource implements RevenueSource
{
    /** Daily base per metric for the first app; later apps are smaller. */
    private const BASE = ['revenue' => 180.0, 'customers' => 42.0, 'trials' => 14.0];

    /** Share of gross revenue left after store fees and taxes. */
    private const PROCEEDS_SHARE = 0.70;

    public function daily(AppDefinition $app, Metric $metric, string $from, string $to): DailySeries
    {
        $scale = 1 / (1 + 1.4 * (Noise::unit($app->id) > 0.5 ? 1 : 0)); // two sizes of app, fixed per id
        $today = CarbonImmutable::now('UTC')->toDateString();

        $values = [];
        for ($day = CarbonImmutable::parse($from, 'UTC'); $day->toDateString() <= $to; $day = $day->addDay()) {
            $date = $day->toDateString();
            if ($date > $today) {
                break;
            }
            $value = match ($metric) {
                Metric::Revenue => Noise::shape(self::BASE['revenue'] * $scale, $date, $app->id.':revenue'),
                Metric::Proceeds => Noise::shape(self::BASE['revenue'] * $scale, $date, $app->id.':revenue') * self::PROCEEDS_SHARE,
                Metric::Customers => round(Noise::shape(self::BASE['customers'] * $scale, $date, $app->id.':customers', 0.3, 1.25)),
                Metric::Trials => round(Noise::shape(self::BASE['trials'] * $scale, $date, $app->id.':trials', 0.35, 1.2)),
            };
            $values[$date] = $date === $today ? round($value * 0.45, $metric->isMoney() ? 2 : 0) : round($value, 2);
        }

        return new DailySeries($values, isset($values[$today]) ? [$today] : []);
    }
}
