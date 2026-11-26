<?php

declare(strict_types=1);

namespace App\Growth;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The last N days up to and including today (UTC), and the N days before them for comparison.
 *
 * Upstream data is fetched once for [previousStart, end]; each day then falls in the current period (a point of the
 * series) or the previous one (added to a single comparison total).
 */
final class Period
{
    public const ALLOWED = [7, 30, 90];

    private function __construct(
        public readonly int $days,
        public readonly string $start,
        public readonly string $end,
        public readonly string $previousStart,
    ) {}

    public static function lastDays(int $days, CarbonImmutable $today): self
    {
        if (! in_array($days, self::ALLOWED, true)) {
            throw new InvalidArgumentException("Unsupported period: {$days} days.");
        }

        $end = $today->utc()->startOfDay();
        $start = $end->subDays($days - 1);

        return new self($days, $start->toDateString(), $end->toDateString(), $start->subDays($days)->toDateString());
    }

    /** @return list<string> Every date of the current period, oldest first. */
    public function dates(): array
    {
        $dates = [];
        for ($day = CarbonImmutable::parse($this->start, 'UTC'); $day->toDateString() <= $this->end; $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    public function isCurrent(string $date): bool
    {
        return $date >= $this->start && $date <= $this->end;
    }

    public function isPrevious(string $date): bool
    {
        return $date >= $this->previousStart && $date < $this->start;
    }
}
