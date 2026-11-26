<?php

declare(strict_types=1);

namespace App\Growth\Sources\Meta;

use App\Growth\AdReport;
use App\Growth\AdRow;
use App\Growth\Contracts\AdSource;
use App\Growth\SourceError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Meta Marketing API, read only (a system-user token with `ads_read`).
 *
 * Three reads: the account (for its currency), its campaigns (for what each one promotes and its status) and the
 * daily insights per campaign. Insights only carry ids and names, so campaigns are joined in to attribute each row.
 */
final class MetaAdsSource implements AdSource
{
    /** Install actions, best first: Meta reports the same installs under several names depending on the setup. */
    public const INSTALL_ACTIONS = ['omni_app_install', 'mobile_app_install', 'app_install'];

    /** Upper bound on pages followed per request, so a cursor bug upstream cannot loop forever. */
    public const MAX_PAGES = 20;

    public function __construct(
        private readonly Http $http,
        private readonly string $token,
        private readonly string $account,
        private readonly string $version = 'v21.0',
    ) {}

    public function report(string $from, string $to): AdReport
    {
        $account = $this->get($this->account, ['fields' => 'currency']);

        $campaigns = [];
        foreach ($this->all("{$this->account}/campaigns", ['fields' => 'effective_status,promoted_object', 'limit' => 200]) as $campaign) {
            $campaigns[$campaign['id']] = $campaign;
        }

        $rows = [];
        $insights = $this->all("{$this->account}/insights", [
            'level' => 'campaign',
            'time_increment' => 1,
            'time_range' => json_encode(['since' => $from, 'until' => $to], JSON_THROW_ON_ERROR),
            'fields' => 'campaign_id,campaign_name,spend,impressions,inline_link_clicks,actions',
            'limit' => 500,
        ]);
        foreach ($insights as $row) {
            $campaign = $campaigns[$row['campaign_id'] ?? ''] ?? [];
            $rows[] = new AdRow(
                date: (string) $row['date_start'],
                campaignId: (string) ($row['campaign_id'] ?? ''),
                campaignName: (string) ($row['campaign_name'] ?? ''),
                spend: (float) ($row['spend'] ?? 0),
                impressions: (int) ($row['impressions'] ?? 0),
                clicks: (int) ($row['inline_link_clicks'] ?? 0),
                installs: self::installs($row),
                status: (string) ($campaign['effective_status'] ?? 'ARCHIVED'),
                applicationId: (string) ($campaign['promoted_object']['application_id'] ?? ''),
                storeUrl: (string) ($campaign['promoted_object']['object_store_url'] ?? ''),
            );
        }

        return new AdReport(strtoupper((string) ($account['currency'] ?? 'USD')), $rows);
    }

    /** @param  array<string, mixed>  $row */
    public static function installs(array $row): int
    {
        $found = [];
        foreach ($row['actions'] ?? [] as $action) {
            $found[$action['action_type'] ?? ''] = (int) ($action['value'] ?? 0);
        }
        foreach (self::INSTALL_ACTIONS as $type) {
            if (isset($found[$type])) {
                return $found[$type];
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function all(string $path, array $query): array
    {
        $rows = [];
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $data = $this->get($path, $query);
            array_push($rows, ...($data['data'] ?? []));
            $after = $data['paging']['cursors']['after'] ?? null;
            if (! $after || empty($data['paging']['next'])) {
                break;
            }
            $query['after'] = $after;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        try {
            $response = $this->http->withToken($this->token)->acceptJson()->timeout(40)
                ->get("https://graph.facebook.com/{$this->version}/{$path}", $query);
        } catch (ConnectionException) {
            throw new SourceError('Meta did not answer. Try again in a minute.');
        }

        if ($response->failed()) {
            // Only Meta's own message: never the URL or the request, which carry the token.
            $message = (string) ($response->json('error.message') ?? '');
            throw new SourceError('Meta answered '.$response->status().($message !== '' ? ": {$message}" : '.'));
        }

        return $response->json() ?? [];
    }
}
