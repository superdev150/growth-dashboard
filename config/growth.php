<?php

return [

    // 'demo' (deterministic sample data, no keys) or 'live' (RevenueCat + Meta Ads).
    'driver' => env('GROWTH_DRIVER', 'demo'),

    // Reporting currency. Revenue is requested in it; ad spend is converted to it.
    'currency' => env('GROWTH_CURRENCY', 'USD'),

    // Both upstream APIs are rate limited (RevenueCat: 25 requests/min per key), so every answer is cached.
    'cache_minutes' => 10,

    /*
     * One entry per app. `revenuecat` is the app's project and key; `ads` says which Meta campaigns belong to it:
     * by the promoted app (Meta application id or store URL) first, then by a campaign-name prefix.
     */
    'apps' => [
        'notes' => [
            'name' => 'Lumen Notes',
            'revenuecat' => ['project' => env('RC_NOTES_PROJECT'), 'key' => env('RC_NOTES_KEY')],
            'ads' => ['application_ids' => [], 'stores' => ['id1000000001', 'com.example.notes'], 'prefixes' => ['LN ']],
        ],
        'trails' => [
            'name' => 'Pocket Trails',
            'revenuecat' => ['project' => env('RC_TRAILS_PROJECT'), 'key' => env('RC_TRAILS_KEY')],
            'ads' => ['application_ids' => [], 'stores' => ['id1000000002', 'com.example.trails'], 'prefixes' => ['PT ']],
        ],
    ],

    'meta' => [
        'token' => env('META_TOKEN'),
        'account' => env('META_AD_ACCOUNT'),
        'version' => 'v21.0',
    ],

    'fx' => [
        'url' => 'https://open.er-api.com/v6/latest/USD',
        'cache_hours' => 12,
        // Units of the ad account's currency per 1 USD, used only when the service has never answered.
        'fallback_rate' => env('META_FALLBACK_RATE'),
    ],
];
