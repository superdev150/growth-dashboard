<?php

declare(strict_types=1);

namespace App\Growth\Contracts;

use App\Growth\AdReport;

interface AdSource
{
    /** Spend and results per campaign per day, both dates included. Throws SourceError when the provider fails. */
    public function report(string $from, string $to): AdReport;
}
