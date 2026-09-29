<?php

namespace Functional\Reminders\Tests\Feature;

use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Support\UnsubscribeLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-014, FR-015 — the one-click unsubscribe links of a reminder email.
 */
class UnsubscribeLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_link_opens_the_page_of_the_web_app(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create(['unsubscribe_version' => 2]);

        $url = app(UnsubscribeLink::class)->forWebPage($setting);

        $this->assertStringStartsWith('http://localhost:3000/rappels/desinscription?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame((string) $setting->user_id, $query['id']);
        $this->assertSame('2', $query['v']);
        $this->assertNotEmpty($query['signature']);
        $this->assertArrayNotHasKey('expires', $query);
    }

    public function test_the_header_link_calls_the_api_directly(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create();

        $url = app(UnsubscribeLink::class)->forMailbox($setting);

        $this->assertStringStartsWith(config('app.url')."/api/reminders/unsubscribe/{$setting->user_id}?", $url);
        $this->assertStringContainsString('v=0', $url);
        $this->assertStringContainsString('signature=', $url);
    }
}
