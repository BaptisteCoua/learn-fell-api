<?php

namespace Functional\Moderation\Tests\Feature;

use Functional\Catalog\Models\Subject;
use Functional\Moderation\Models\ModerationDecision;
use Functional\Moderation\Models\Report;
use Functional\Moderation\Tests\Concerns\Moderates;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 004, FR-011 and FR-020 — a reporter or a moderator whose deletion is pending is no
 * longer named, and is null once erased; the report and the decision stay.
 */
class ModerationNamesTest extends TestCase
{
    use Moderates, RefreshDatabase;

    public function test_a_reporter_whose_deletion_is_pending_is_no_longer_named(): void
    {
        $reporter = User::factory()->pendingDeletion()->create();
        $report = Report::factory()->for(Subject::factory()->published())->create(['reporter_id' => $reporter->getKey()]);

        $response = $this->search($this->moderator(), 'reports', [
            'filters' => [['field' => 'id', 'value' => $report->getKey()]],
            'includes' => [['relation' => 'reporter']],
        ]);

        $response->assertOk();
        $this->assertSame($reporter->getKey(), $response->json('data.0.reporter.id'));
        $this->assertNull($response->json('data.0.reporter.display_name'));
    }

    public function test_a_report_whose_reporter_was_erased_stays_without_reporter(): void
    {
        $report = Report::factory()->for(Subject::factory()->published())->create(['reporter_id' => null]);

        $response = $this->search($this->moderator(), 'reports', [
            'filters' => [['field' => 'id', 'value' => $report->getKey()]],
            'includes' => [['relation' => 'reporter']],
        ]);

        $response->assertOk();
        $this->assertSame([$report->getKey()], collect($response->json('data'))->pluck('id')->all());
        $this->assertNull($response->json('data.0.reporter'));
    }

    public function test_a_moderator_whose_deletion_is_pending_is_no_longer_named_in_the_history(): void
    {
        $admin = User::factory()->pendingDeletion()->create();
        $decision = ModerationDecision::factory()->create(['admin_id' => $admin->getKey()]);
        $erased = ModerationDecision::factory()->create(['admin_id' => null]);

        $response = $this->search($this->moderator(), 'moderation-decisions', [
            'includes' => [['relation' => 'admin']],
        ]);

        $response->assertOk();
        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertNull($byId[$decision->getKey()]['admin']['display_name']);
        $this->assertNull($byId[$erased->getKey()]['admin']);
    }
}
