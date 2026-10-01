<?php

namespace Functional\Reminders\Tests\Feature;

use Functional\Reminders\Enums\EmailDisabledReason;
use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Support\UnsubscribeLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-014, FR-015, FR-016 — user story 4: one click, without logging in, turns the emails off.
 */
class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private function mailboxLink(ReminderSetting $setting): string
    {
        return app(UnsubscribeLink::class)->forMailbox($setting);
    }

    public function test_a_valid_link_turns_the_reminder_emails_off(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create();

        $this->post($this->mailboxLink($setting))->assertNoContent();

        $setting->refresh();
        $this->assertFalse($setting->email_enabled);
        $this->assertSame(EmailDisabledReason::Unsubscribed, $setting->email_disabled_reason);
        $this->assertNull($setting->next_reminder_at);
    }

    public function test_the_link_of_an_account_being_deleted_or_erased_is_not_valid(): void
    {
        $pending = ReminderSetting::factory()->emailEnabled()->create();
        $pendingLink = $this->mailboxLink($pending);
        $pending->user->forceFill(['deletion_requested_at' => now()])->save();
        $erased = ReminderSetting::factory()->emailEnabled()->create();
        $erasedLink = $this->mailboxLink($erased);
        $erased->user->delete();

        $pendingAnswer = $this->post($pendingLink)->assertForbidden();
        $erasedAnswer = $this->post($erasedLink)->assertForbidden();

        $this->assertSame($erasedAnswer->json(), $pendingAnswer->json());
        $this->assertTrue($pending->fresh()->email_enabled);
    }

    public function test_the_same_link_used_twice_answers_the_same(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create();
        $link = $this->mailboxLink($setting);

        $this->post($link)->assertNoContent();
        $this->post($link)->assertNoContent();

        $this->assertSame(EmailDisabledReason::Unsubscribed, $setting->fresh()->email_disabled_reason);
    }

    public function test_the_one_click_post_of_a_mailbox_is_accepted_without_a_session(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create();

        $this->call('POST', $this->mailboxLink($setting), ['List-Unsubscribe' => 'One-Click'])->assertNoContent();

        $this->assertFalse($setting->fresh()->email_enabled);
    }

    public function test_the_notifications_keep_going(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create();
        PushSubscription::factory()->for($setting, 'subscribable')->create();

        $this->post($this->mailboxLink($setting))->assertNoContent();

        $setting->refresh();
        $this->assertSame(1, $setting->pushSubscriptions()->count());
        $this->assertNotNull($setting->next_reminder_at);
    }

    public function test_an_altered_signature_changes_nothing(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create();
        $altered = preg_replace('/signature=(.)/', 'signature=x', $this->mailboxLink($setting));

        $this->post($altered)->assertForbidden()->assertExactJson(['code' => 'invalid_link', 'message' => 'Ce lien n’est pas valide.']);

        $this->assertTrue($setting->fresh()->email_enabled);
    }

    public function test_a_link_sent_before_the_email_was_turned_back_on_changes_nothing(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create(['unsubscribe_version' => 1]);
        $oldLink = $this->mailboxLink($setting);
        $setting->update(['unsubscribe_version' => 2]);

        $this->post($oldLink)->assertForbidden()->assertJson(['code' => 'invalid_link']);

        $this->assertTrue($setting->fresh()->email_enabled);
    }

    public function test_every_invalid_link_answers_alike_without_telling_which_account_it_is(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create();
        $settingOfAnUnknownAccount = ReminderSetting::factory()->emailEnabled()->create();
        $unknownLink = $this->mailboxLink($settingOfAnUnknownAccount);
        $settingOfAnUnknownAccount->user->delete();
        $altered = preg_replace('/signature=(.)/', 'signature=x', $this->mailboxLink($setting));

        $unknown = $this->post($unknownLink);
        $forged = $this->post($altered);

        $unknown->assertForbidden();
        $forged->assertForbidden();
        $this->assertSame($forged->json(), $unknown->json());
    }

    public function test_the_links_are_limited_to_6_calls_a_minute(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create();
        $link = $this->mailboxLink($setting);

        foreach (range(1, 6) as $call) {
            $this->post($link)->assertNoContent();
        }

        $this->post($link)->assertTooManyRequests();
    }
}
