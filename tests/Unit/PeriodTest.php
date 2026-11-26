<?php

namespace Tests\Unit;

use App\Growth\Period;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PeriodTest extends TestCase
{
    public function test_last_seven_days_end_today_and_compare_with_the_seven_before(): void
    {
        $period = Period::lastDays(7, CarbonImmutable::parse('2026-03-10 18:30', 'UTC'));

        $this->assertSame('2026-03-04', $period->start);
        $this->assertSame('2026-03-10', $period->end);
        $this->assertSame('2026-02-25', $period->previousStart);
        $this->assertCount(7, $period->dates());
        $this->assertTrue($period->isPrevious('2026-03-03'));
        $this->assertFalse($period->isPrevious('2026-03-04'));
        $this->assertFalse($period->isPrevious('2026-02-24'));
    }

    public function test_today_is_taken_in_utc(): void
    {
        // 23:30 in Tegucigalpa is already the next day in UTC.
        $period = Period::lastDays(7, CarbonImmutable::parse('2026-03-10 23:30', 'America/Tegucigalpa'));

        $this->assertSame('2026-03-11', $period->end);
    }

    public function test_spans_month_and_leap_day_boundaries(): void
    {
        $dates = Period::lastDays(30, CarbonImmutable::parse('2028-03-15', 'UTC'))->dates();

        $this->assertSame('2028-02-15', $dates[0]);
        $this->assertContains('2028-02-29', $dates);
        $this->assertCount(30, array_unique($dates));
    }

    public function test_rejects_other_lengths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Period::lastDays(14, CarbonImmutable::now());
    }
}
