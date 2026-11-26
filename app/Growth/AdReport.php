<?php

declare(strict_types=1);

namespace App\Growth;

final class AdReport
{
    /** @param  list<AdRow>  $rows */
    public function __construct(
        public readonly string $currency,
        public readonly array $rows,
    ) {}

    /** @param  array{currency: string, rows: list<array<string, mixed>>}  $data */
    public static function fromArray(array $data): self
    {
        return new self($data['currency'], array_map(AdRow::fromArray(...), $data['rows']));
    }

    /** @return array{currency: string, rows: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['currency' => $this->currency, 'rows' => array_map(fn (AdRow $row) => $row->toArray(), $this->rows)];
    }
}
