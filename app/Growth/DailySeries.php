<?php

declare(strict_types=1);

namespace App\Growth;

/** Values by date, plus the dates the provider is still computing (usually today). */
final class DailySeries
{
    /**
     * @param  array<string, float>  $values  'Y-m-d' => value
     * @param  list<string>  $incomplete
     */
    public function __construct(
        public readonly array $values,
        public readonly array $incomplete = [],
    ) {}

    /** @param  array{values: array<string, float>, incomplete: list<string>}  $data */
    public static function fromArray(array $data): self
    {
        return new self($data['values'], $data['incomplete']);
    }

    /** @return array{values: array<string, float>, incomplete: list<string>} */
    public function toArray(): array
    {
        return ['values' => $this->values, 'incomplete' => $this->incomplete];
    }
}
