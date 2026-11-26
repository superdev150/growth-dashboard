<?php

declare(strict_types=1);

namespace App\Growth\Contracts;

use App\Growth\AppDefinition;
use App\Growth\DailySeries;
use App\Growth\Metric;

interface RevenueSource
{
    /** Daily values of one metric for one app, both dates included. Throws SourceError when the provider fails. */
    public function daily(AppDefinition $app, Metric $metric, string $from, string $to): DailySeries;
}
