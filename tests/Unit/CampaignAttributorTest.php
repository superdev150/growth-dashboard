<?php

namespace Tests\Unit;

use App\Growth\AdRow;
use App\Growth\AppDefinition;
use App\Growth\CampaignAttributor;
use PHPUnit\Framework\TestCase;

class CampaignAttributorTest extends TestCase
{
    private CampaignAttributor $attributor;

    protected function setUp(): void
    {
        $this->attributor = new CampaignAttributor([
            'notes' => new AppDefinition('notes', 'Notes', applicationIds: ['555'], stores: ['id111'], prefixes: ['LN ']),
            'trails' => new AppDefinition('trails', 'Trails', stores: ['com.example.trails'], prefixes: ['PT ']),
        ]);
    }

    private function row(string $name, string $applicationId = '', string $store = ''): AdRow
    {
        return new AdRow('2026-01-01', '1', $name, 10, applicationId: $applicationId, storeUrl: $store);
    }

    public function test_by_meta_application_id(): void
    {
        $this->assertSame('notes', $this->attributor->appFor($this->row('Anything', '555')));
    }

    public function test_by_store_url(): void
    {
        $this->assertSame('trails', $this->attributor->appFor($this->row('x', store: 'https://play.google.com/store/apps/details?id=com.example.trails')));
    }

    public function test_what_is_promoted_beats_a_misleading_name(): void
    {
        // A campaign duplicated from Notes and repointed to Trails keeps its old name.
        $this->assertSame('trails', $this->attributor->appFor($this->row('LN copy of best ad', store: 'com.example.trails')));
    }

    public function test_name_prefix_when_nothing_is_promoted(): void
    {
        $this->assertSame('notes', $this->attributor->appFor($this->row('ln reels · latam')));
        $this->assertSame('trails', $this->attributor->appFor($this->row('PT retargeting')));
    }

    public function test_prefix_must_be_at_the_start(): void
    {
        $this->assertNull($this->attributor->appFor($this->row('Brand LN test')));
    }

    public function test_unknown_campaigns_belong_to_no_app(): void
    {
        $this->assertNull($this->attributor->appFor($this->row('Brand awareness')));
        $this->assertNull($this->attributor->appFor($this->row('Other', '999', 'id999')));
    }
}
