<?php

declare(strict_types=1);

namespace App\Growth\Fx;

/** Units of a currency per 1 USD, and where that number came from (shown under the spend figures). */
final class Rate
{
    public function __construct(
        public readonly float $perUsd,
        public readonly string $source,
        public readonly string $date = '',
    ) {}

    /** @return array{per_usd: float, source: string, date: string} */
    public function toArray(): array
    {
        return ['per_usd' => $this->perUsd, 'source' => $this->source, 'date' => $this->date];
    }
}
