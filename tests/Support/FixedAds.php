<?php

namespace Tests\Support;

use App\Growth\AdReport;
use App\Growth\AdRow;
use App\Growth\Contracts\AdSource;
use App\Growth\SourceError;

final class FixedAds implements AdSource
{
    /** @param  list<AdRow>|null  $rows  null = the account fails */
    public function __construct(private ?array $rows, private string $currency = 'USD') {}

    public function report(string $from, string $to): AdReport
    {
        if ($this->rows === null) {
            throw new SourceError('Meta answered 500.');
        }

        return new AdReport($this->currency, array_values(array_filter($this->rows, fn ($r) => $r->date >= $from && $r->date <= $to)));
    }
}
