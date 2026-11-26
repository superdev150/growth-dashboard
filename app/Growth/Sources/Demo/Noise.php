<?php

declare(strict_types=1);

namespace App\Growth\Sources\Demo;

use Carbon\CarbonImmutable;

/** Deterministic pseudo-random numbers: the same date and seed always give the same value, on any machine. */
final class Noise
{
    /** A number in [0, 1). */
    public static function unit(string ...$seed): float
    {
        return (crc32(implode('|', $seed)) % 10_000) / 10_000;
    }

    /** base × slow growth × weekly rhythm × ±spread noise. */
    public static function shape(float $base, string $date, string $seed, float $spread = 0.25, float $weekend = 1.15): float
    {
        $day = CarbonImmutable::parse($date, 'UTC');
        $growth = 1 + 0.002 * $day->dayOfYear;
        $rhythm = $day->isWeekend() ? $weekend : 1.0;

        return $base * $growth * $rhythm * (1 - $spread + 2 * $spread * self::unit($seed, $date));
    }
}
