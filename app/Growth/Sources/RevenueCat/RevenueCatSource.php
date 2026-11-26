<?php

declare(strict_types=1);

namespace App\Growth\Sources\RevenueCat;

use App\Growth\AppDefinition;
use App\Growth\Contracts\RevenueSource;
use App\Growth\DailySeries;
use App\Growth\Metric;
use App\Growth\SourceError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * RevenueCat API v2 charts, one project (and secret key) per app.
 *
 *   GET /v2/projects/{project}/charts/{chart}?start_date&end_date&resolution=0&currency&selectors
 *   → { measures: [{display_name}], values: [{cohort, measure, value, incomplete, segment?}] }
 *
 * A chart carries several measures (revenue comes with transaction counts), so the measure is picked by name and
 * falls back to the first. `cohort` is a timestamp that has arrived both in seconds and in milliseconds.
 */
final class RevenueCatSource implements RevenueSource
{
    private const BASE = 'https://api.revenuecat.com/v2/projects/';

    public function __construct(
        private readonly Http $http,
        private readonly string $currency,
    ) {}

    public function daily(AppDefinition $app, Metric $metric, string $from, string $to): DailySeries
    {
        if (! $app->hasRevenueCat()) {
            throw new SourceError("{$app->name}: RevenueCat is not configured.");
        }

        $query = ['start_date' => $from, 'end_date' => $to, 'resolution' => '0', 'realtime' => 'true'];
        if ($metric->isMoney()) {
            $query['currency'] = $this->currency;
        }
        if ($metric->selectors() !== []) {
            $query['selectors'] = json_encode($metric->selectors(), JSON_THROW_ON_ERROR);
        }

        return self::parse($this->get($app, 'charts/'.$metric->chart(), $query), $metric->measure());
    }

    /** @param  array<string, mixed>  $chart */
    public static function parse(array $chart, string $measureName): DailySeries
    {
        $measure = 0;
        foreach ($chart['measures'] ?? [] as $index => $measureInfo) {
            if (strcasecmp((string) ($measureInfo['display_name'] ?? ''), $measureName) === 0) {
                $measure = (int) $index;
                break;
            }
        }

        $values = [];
        $incomplete = [];
        foreach ($chart['values'] ?? [] as $point) {
            if (! is_array($point) || (int) ($point['measure'] ?? 0) !== $measure) {
                continue;
            }
            // Segmented charts repeat each day per segment; only the total (no segment, or segment 0) counts.
            if (($point['segment'] ?? null) !== null && $point['segment'] !== 0) {
                continue;
            }
            $timestamp = (int) ($point['cohort'] ?? 0);
            if ($timestamp > 100_000_000_000) {
                $timestamp = intdiv($timestamp, 1000);
            }
            $date = gmdate('Y-m-d', $timestamp);
            $values[$date] = ($values[$date] ?? 0.0) + (float) ($point['value'] ?? 0);
            if (! empty($point['incomplete'])) {
                $incomplete[] = $date;
            }
        }
        ksort($values);

        return new DailySeries($values, array_values(array_unique($incomplete)));
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function get(AppDefinition $app, string $path, array $query): array
    {
        try {
            $response = $this->http->withToken((string) $app->revenueCatKey)
                ->acceptJson()
                ->timeout(20)
                ->get(self::BASE.rawurlencode((string) $app->revenueCatProject).'/'.$path, $query);
        } catch (ConnectionException) {
            throw new SourceError("{$app->name}: RevenueCat did not answer. Try again in a minute.");
        }

        if ($response->failed()) {
            throw new SourceError("{$app->name}: ".match ($response->status()) {
                401 => 'RevenueCat rejected the key (401). It must be a v2 secret key.',
                403 => 'the key lacks the charts permission (403).',
                404 => 'RevenueCat does not know this project (404). Check the project id.',
                429 => 'RevenueCat rate limit reached (25 requests per minute). Try again in a minute.',
                default => 'RevenueCat answered '.$response->status().'.',
            });
        }

        return $response->json() ?? [];
    }
}
