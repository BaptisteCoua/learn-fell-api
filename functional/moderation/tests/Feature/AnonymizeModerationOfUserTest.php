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
 * Feature 004, FR-020 — reports and decisions outlive the erased account, without it.
 */
class AnonymizeModerationOfUserTest extends TestCase
{
    use Moderates, RefreshDatabase;

    public function test_reports_and_decisions_of_an_erased_account_stay_without_it(): void
    {
        $leaving = User::factory()->pendingDeletion(daysAgo: 31)->create();
        $report = Report::factory()->for(Subject::factory()->published())->create(['reporter_id' => $leaving->id]);
        $decision = ModerationDecision::factory()->create(['admin_id' => $leaving->id]);

        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

        $this->assertModelMissing($leaving);
        $this->assertNull($report->fresh()->reporter_id);
        $this->assertNull($decision->fresh()->admin_id);

        $queue = $this->search($this->moderator(), 'reports', ['includes' => [['relation' => 'reporter']]]);
        $this->assertSame([$report->id], collect($queue->json('data'))->pluck('id')->all());
        $this->assertNull($queue->json('data.0.reporter'));
    }

    public function test_an_erased_subject_takes_its_reports_and_leaves_its_decisions(): void
    {
        $author = User::factory()->pendingDeletion(keepsPublishedSubjects: false, daysAgo: 31)->create();
        $subject = Subject::factory()->for($author, 'author')->published()->create(['title' => 'Commandes SQL piégées']);
        $report = Report::factory()->for($subject)->create();
        $decision = ModerationDecision::factory()->create(['subject_id' => $subject->id, 'subject_title' => $subject->title]);

        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

        $this->assertModelMissing($subject);
        $this->assertModelMissing($report);
        $this->assertSame('Commandes SQL piégées', $decision->fresh()->subject_title);
    }
}
