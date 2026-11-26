<?php

declare(strict_types=1);

namespace App\Growth;

use App\Growth\Contracts\AdSource;
use App\Growth\Contracts\RevenueSource;
use App\Growth\Fx\ExchangeRates;
use Psr\Log\LoggerInterface;

/**
 * Builds the dashboard: ad spend and installs (from the ad account) next to customers and revenue (from each app's
 * subscription backend), for a period and the one before it.
 *
 * Failure isolation is the point: an app whose key was revoked, or an ad account that is down, gets an `error` in
 * its own block and zeros in its series, and the rest of the dashboard still loads. `partial` tells the UI that
 * the totals are missing something.
 */
final class GrowthReport
{
    /** Per-app series. Spend and installs come from ads, the rest from the subscription backend. */
    public const SERIES = ['spend', 'installs', 'revenue', 'proceeds', 'customers', 'trials'];

    /**
     * @param  array<string, AppDefinition>  $apps
     */
    public function __construct(
        private readonly array $apps,
        private readonly RevenueSource $revenue,
        private readonly ?AdSource $ads,
        private readonly ExchangeRates $rates,
        private readonly string $currency,
        private readonly LoggerInterface $log,
        private readonly string $driver = 'live',
    ) {}

    /** @return array<string, mixed> */
    public function build(Period $period): array
    {
        $dates = $period->dates();
        $index = array_flip($dates);

        $apps = [];
        foreach ($this->apps as $id => $app) {
            $apps[$id] = [
                'id' => $id,
                'name' => $app->name,
                'error' => null,
                'incomplete' => [],
                'series' => array_fill_keys(self::SERIES, array_fill(0, count($dates), 0.0)),
                'previous' => array_fill_keys(self::SERIES, 0.0),
            ];
        }

        $ads = $this->addAds($period, $index, $apps);
        $this->addRevenue($period, $index, $apps);

        $partial = $ads['error'] !== null;
        foreach ($apps as &$app) {
            $partial = $partial || $app['error'] !== null;
            foreach (self::SERIES as $metric) {
                $money = in_array($metric, ['spend', 'revenue', 'proceeds'], true);
                $app['series'][$metric] = array_map(fn (float $v) => $money ? round($v, 2) : (int) round($v), $app['series'][$metric]);
                $app['previous'][$metric] = $money ? round($app['previous'][$metric], 2) : (int) round($app['previous'][$metric]);
            }
        }
        unset($app);

        return [
            'driver' => $this->driver,
            'currency' => $this->currency,
            'days' => $period->days,
            'dates' => $dates,
            'generated_at' => now()->toIso8601String(),
            'partial' => $partial,
            'totals' => $this->totals($apps),
            'apps' => array_values($apps),
            'ads' => $ads,
        ];
    }

    /**
     * @param  array<string, int>  $index
     * @param  array<string, array<string, mixed>>  $apps
     * @return array<string, mixed>
     */
    private function addAds(Period $period, array $index, array &$apps): array
    {
        $ads = ['error' => null, 'account_currency' => null, 'rate' => null, 'campaigns' => [], 'unassigned' => ['spend' => 0.0, 'campaigns' => 0]];
        if ($this->ads === null) {
            return ['error' => 'The ad account is not configured.'] + $ads;
        }

        try {
            $report = $this->ads->report($period->previousStart, $period->end);
            // spend × factor = spend in the reporting currency
            $from = $this->rates->perUsd($report->currency);
            $to = $this->rates->perUsd($this->currency);
            $factor = $to->perUsd / $from->perUsd;
        } catch (SourceError $e) {
            $this->log->warning('Growth: ads failed', ['error' => $e->getMessage()]);

            return ['error' => $e->getMessage()] + $ads;
        }

        $ads['account_currency'] = $report->currency;
        if ($report->currency !== $this->currency) {
            $ads['rate'] = ['factor' => round($factor, 6)] + $from->toArray();
        }

        $attributor = new CampaignAttributor($this->apps);
        $campaigns = [];
        foreach ($report->rows as $row) {
            $app = $attributor->appFor($row);
            $spend = $row->spend * $factor;

            if ($app !== null) {
                $values = ['spend' => $spend, 'installs' => $row->installs];
                foreach ($values as $metric => $value) {
                    if (isset($index[$row->date])) {
                        $apps[$app]['series'][$metric][$index[$row->date]] += $value;
                    } elseif ($period->isPrevious($row->date)) {
                        $apps[$app]['previous'][$metric] += $value;
                    }
                }
            }

            if ($period->isCurrent($row->date)) {
                $campaigns[$row->campaignId] ??= [
                    'id' => $row->campaignId, 'name' => $row->campaignName, 'app' => $app, 'status' => $row->status,
                    'spend' => 0.0, 'impressions' => 0, 'clicks' => 0, 'installs' => 0,
                ];
                $campaigns[$row->campaignId]['spend'] += $spend;
                $campaigns[$row->campaignId]['impressions'] += $row->impressions;
                $campaigns[$row->campaignId]['clicks'] += $row->clicks;
                $campaigns[$row->campaignId]['installs'] += $row->installs;
            }
        }

        foreach ($campaigns as $campaign) {
            $campaign['spend'] = round($campaign['spend'], 2);
            if ($campaign['app'] === null) {
                $ads['unassigned']['spend'] += $campaign['spend'];
                $ads['unassigned']['campaigns']++;

                continue;
            }
            $campaign['cpi'] = $campaign['installs'] > 0 ? round($campaign['spend'] / $campaign['installs'], 2) : null;
            $ads['campaigns'][] = $campaign;
        }
        usort($ads['campaigns'], fn (array $a, array $b) => $b['spend'] <=> $a['spend']);
        $ads['unassigned']['spend'] = round($ads['unassigned']['spend'], 2);

        return $ads;
    }

    /**
     * @param  array<string, int>  $index
     * @param  array<string, array<string, mixed>>  $apps
     */
    private function addRevenue(Period $period, array $index, array &$apps): void
    {
        foreach ($this->apps as $id => $app) {
            try {
                // Fetch everything first, so a failure halfway never leaves an app with half its metrics.
                $fetched = [];
                foreach (Metric::cases() as $metric) {
                    $fetched[$metric->value] = $this->revenue->daily($app, $metric, $period->previousStart, $period->end);
                }
            } catch (SourceError $e) {
                $this->log->warning('Growth: revenue failed', ['app' => $id, 'error' => $e->getMessage()]);
                $apps[$id]['error'] = $e->getMessage();

                continue;
            }

            foreach ($fetched as $metric => $series) {
                foreach ($series->values as $date => $value) {
                    if (isset($index[$date])) {
                        $apps[$id]['series'][$metric][$index[$date]] += $value;
                    } elseif ($period->isPrevious($date)) {
                        $apps[$id]['previous'][$metric] += $value;
                    }
                }
                $apps[$id]['incomplete'] = array_values(array_unique([...$apps[$id]['incomplete'], ...$series->incomplete]));
            }
        }
    }

    /**
     * Sums over all apps, with the previous period and the change. Ratios are computed from the sums, never averaged.
     *
     * @param  array<string, array<string, mixed>>  $apps
     * @return array<string, array{value: float|int|null, previous: float|int|null, change: float|null}>
     */
    private function totals(array $apps): array
    {
        $current = array_fill_keys([...self::SERIES, 'spend_with_revenue'], 0.0);
        $previous = $current;
        foreach ($apps as $app) {
            foreach (self::SERIES as $metric) {
                $current[$metric] += array_sum($app['series'][$metric]);
                $previous[$metric] += $app['previous'][$metric];
            }
            // An app whose revenue failed to load still has spend; leaving that spend in would understate ROAS.
            if ($app['error'] === null) {
                $current['spend_with_revenue'] += array_sum($app['series']['spend']);
                $previous['spend_with_revenue'] += $app['previous']['spend'];
            }
        }

        $ratios = [
            'cpi' => fn (array $t) => $t['installs'] > 0 ? $t['spend'] / $t['installs'] : null,   // cost per install
            'roas' => fn (array $t) => $t['spend_with_revenue'] > 0 ? $t['revenue'] / $t['spend_with_revenue'] : null, // revenue per unit of spend
        ];

        $totals = [];
        foreach (self::SERIES as $metric) {
            $totals[$metric] = $this->compare($current[$metric], $previous[$metric]);
        }
        foreach ($ratios as $name => $ratio) {
            $totals[$name] = $this->compare($ratio($current), $ratio($previous));
        }

        return $totals;
    }

    /** @return array{value: float|null, previous: float|null, change: float|null} */
    private function compare(?float $value, ?float $previous): array
    {
        return [
            'value' => $value === null ? null : round($value, 2),
            'previous' => $previous === null ? null : round($previous, 2),
            // Relative change; none when there is nothing to compare with (a new app, or no spend before).
            'change' => $value !== null && $previous !== null && $previous != 0.0 ? round(($value - $previous) / abs($previous), 4) : null,
        ];
    }
}
