<?php

declare(strict_types=1);

namespace App\Growth;

/**
 * Decides which app an ad campaign is for.
 *
 * What the campaign promotes wins (Meta application id, then store URL), because names drift and get copied. Only a
 * campaign that does not say (a traffic or awareness campaign) falls back to its name prefix. Campaigns that match
 * nothing are someone else's spend in the same ad account: kept out of every app's numbers and reported apart.
 */
final class CampaignAttributor
{
    /** @param  array<string, AppDefinition>  $apps */
    public function __construct(private readonly array $apps) {}

    public function appFor(AdRow $row): ?string
    {
        foreach ($this->apps as $app) {
            if ($row->applicationId !== '' && in_array($row->applicationId, $app->applicationIds, true)) {
                return $app->id;
            }
            foreach ($app->stores as $store) {
                if ($row->storeUrl !== '' && str_contains($row->storeUrl, $store)) {
                    return $app->id;
                }
            }
        }

        foreach ($this->apps as $app) {
            foreach ($app->prefixes as $prefix) {
                if ($prefix !== '' && stripos($row->campaignName, $prefix) === 0) {
                    return $app->id;
                }
            }
        }

        return null;
    }
}
