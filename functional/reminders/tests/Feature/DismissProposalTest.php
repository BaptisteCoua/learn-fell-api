<?php

namespace Functional\Reminders\Tests\Feature;

use Functional\Reminders\Tests\Concerns\ManagesReminders;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-002 — "Plus tard" in the proposal after the first "Apprendre ce sujet".
 */
class DismissProposalTest extends TestCase
{
    use ManagesReminders, RefreshDatabase;

    public function test_later_marks_the_proposal_as_seen_and_turns_nothing_on(): void
    {
        $user = User::factory()->create();

        $this->dismissProposal($user)->assertOk();

        $setting = $this->settingOf($user);
        $this->assertNotNull($setting->proposal_seen_at);
        $this->assertFalse($setting->email_enabled);
        $this->assertNull($setting->activated_at);
        $this->assertNull($setting->next_reminder_at);
    }

    public function test_later_only_touches_the_account_that_chose_it(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->dismissProposal($user)->assertOk();

        $this->assertNull($this->settingOf($other)->proposal_seen_at);
    }
}
