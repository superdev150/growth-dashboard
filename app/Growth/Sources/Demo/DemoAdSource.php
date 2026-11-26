<?php

declare(strict_types=1);

namespace App\Growth\Sources\Demo;

use App\Growth\AdReport;
use App\Growth\AdRow;
use App\Growth\Contracts\AdSource;
use Carbon\CarbonImmutable;

/**
 * A small ad account: campaigns attributed by store URL, one by name prefix only, and one that belongs to no app
 * (it shows up as unassigned spend, as in a real shared account).
 */
final class DemoAdSource implements AdSource
{
    /** id => [name, store URL, daily spend, cost per install] */
    private const CAMPAIGNS = [
        '2384001' => ['LN · App installs · US', 'https://apps.apple.com/app/id1000000001', 46.0, 1.9],
        '2384002' => ['LN Reels · LATAM', '', 21.0, 0.55],
        '2384003' => ['PT · App installs · iOS', 'https://apps.apple.com/app/id1000000002', 28.0, 1.4],
        '2384004' => ['Brand awareness', '', 7.5, 0.0],
    ];

    public function report(string $from, string $to): AdReport
    {
        $today = CarbonImmutable::now('UTC')->toDateString();
        $rows = [];
        for ($day = CarbonImmutable::parse($from, 'UTC'); $day->toDateString() <= $to && $day->toDateString() <= $today; $day = $day->addDay()) {
            $date = $day->toDateString();
            foreach (self::CAMPAIGNS as $id => [$name, $store, $spend, $cpi]) {
                $amount = round(Noise::shape($spend, $date, "ad:{$id}", 0.2, 1.0) * ($date === $today ? 0.5 : 1), 2);
                $installs = $cpi > 0 ? (int) round($amount / ($cpi * (0.8 + 0.4 * Noise::unit("cpi:{$id}", $date)))) : 0;
                $clicks = (int) round(($installs ?: $amount / 0.4) * 3.2);
                $rows[] = new AdRow($date, (string) $id, $name, $amount, $clicks * 38, $clicks, $installs, 'ACTIVE', '', $store);
            }
        }

        return new AdReport('USD', $rows);
    }
}
