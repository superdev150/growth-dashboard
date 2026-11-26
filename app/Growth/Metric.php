<?php

declare(strict_types=1);

namespace App\Growth;

/** The subscription metrics the dashboard reads per app, and where RevenueCat keeps each one. */
enum Metric: string
{
    case Revenue = 'revenue';      // gross, what customers paid
    case Proceeds = 'proceeds';    // after store fees and taxes
    case Customers = 'customers';  // new customers
    case Trials = 'trials';        // new trials

    public function chart(): string
    {
        return match ($this) {
            self::Revenue, self::Proceeds => 'revenue',
            self::Customers => 'customers_new',
            self::Trials => 'trials_new',
        };
    }

    /** The measure's display name inside the chart (a chart can carry several measures). */
    public function measure(): string
    {
        return match ($this) {
            self::Revenue, self::Proceeds => 'Revenue',
            self::Customers => 'New Customers',
            self::Trials => 'New Trials',
        };
    }

    /** @return array<string, string> */
    public function selectors(): array
    {
        return match ($this) {
            self::Revenue => ['revenue_type' => 'revenue'],
            self::Proceeds => ['revenue_type' => 'proceeds'],
            default => [],
        };
    }

    public function isMoney(): bool
    {
        return $this === self::Revenue || $this === self::Proceeds;
    }
}
