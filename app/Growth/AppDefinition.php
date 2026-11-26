<?php

declare(strict_types=1);

namespace App\Growth;

/** An app on the dashboard: its RevenueCat project and the rules that tie ad campaigns to it. */
final class AppDefinition
{
    /**
     * @param  list<string>  $applicationIds  Meta application ids of the app
     * @param  list<string>  $stores  fragments of its store URLs (App Store id, Play package)
     * @param  list<string>  $prefixes  campaign-name prefixes, used when a campaign does not say what it promotes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $revenueCatProject = null,
        public readonly ?string $revenueCatKey = null,
        public readonly array $applicationIds = [],
        public readonly array $stores = [],
        public readonly array $prefixes = [],
    ) {}

    /** @param  array<string, mixed>  $config */
    public static function fromConfig(string $id, array $config): self
    {
        return new self(
            id: $id,
            name: (string) ($config['name'] ?? $id),
            revenueCatProject: $config['revenuecat']['project'] ?? null ?: null,
            revenueCatKey: $config['revenuecat']['key'] ?? null ?: null,
            applicationIds: array_values(array_map('strval', $config['ads']['application_ids'] ?? [])),
            stores: array_values(array_map('strval', $config['ads']['stores'] ?? [])),
            prefixes: array_values(array_map('strval', $config['ads']['prefixes'] ?? [])),
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $config
     * @return array<string, self>
     */
    public static function allFromConfig(array $config): array
    {
        $apps = [];
        foreach ($config as $id => $app) {
            $apps[(string) $id] = self::fromConfig((string) $id, $app);
        }

        return $apps;
    }

    public function hasRevenueCat(): bool
    {
        return $this->revenueCatProject !== null && $this->revenueCatKey !== null;
    }
}
