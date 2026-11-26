<?php

declare(strict_types=1);

namespace App\Growth;

/** One campaign on one day, in the ad account's currency. */
final class AdRow
{
    public function __construct(
        public readonly string $date,
        public readonly string $campaignId,
        public readonly string $campaignName,
        public readonly float $spend,
        public readonly int $impressions = 0,
        public readonly int $clicks = 0,
        public readonly int $installs = 0,
        public readonly string $status = '',
        public readonly string $applicationId = '',
        public readonly string $storeUrl = '',
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
