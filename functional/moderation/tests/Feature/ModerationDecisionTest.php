<?php

namespace Functional\Moderation\Tests\Feature;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Moderation\Models\ModerationDecision;
use Functional\Moderation\Models\Report;
use Functional\Moderation\Tests\Concerns\Moderates;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-030 to FR-034 — user story 5.
 */
class ModerationDecisionTest extends TestCase
{
    use Moderates, RefreshDatabase;

    public function test_the_queue_lists_pending_reports_oldest_first_with_their_subject(): void
    {
        $recent = Report::factory()->create(['created_at' => now()->subHour()]);
        $oldest = Report::factory()->create(['created_at' => now()->subDays(2)]);
        Report::factory()->create(['status' => 'closed']);

        $response = $this->search($this->moderator(), 'reports', [
            'filters' => [['field' => 'status', 'value' => 'pending']],
            'includes' => [['relation' => 'subject'], ['relation' => 'reporter']],
        ]);

        $this->assertSame([$oldest->id, $recent->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertNotEmpty($response->json('data.0.subject.title'));
        $this->assertNotEmpty($response->json('data.0.reporter.display_name'));
    }

    public function test_ignoring_closes_the_reports_and_keeps_the_subject_published(): void
    {
        $subject = Subject::factory()->published()->create();
        Report::factory()->count(2)->for($subject)->create();

        $this->decide($this->moderator(), $subject, 'ignored')->assertOk();

        $this->assertSame(SubjectStatus::Published, $subject->fresh()->status);
        $this->assertSame(0, Report::query()->where('status', 'pending')->count());
    }

    public function test_retiring_needs_a_reason(): void
    {
        $subject = Subject::factory()->published()->create();

        $this->decide($this->moderator(), $subject, 'retired', '  ')
            ->assertUnprocessable()
            ->assertJson(['code' => 'reason_required']);

        $this->assertSame(SubjectStatus::Published, $subject->fresh()->status);
        $this->assertDatabaseCount('moderation_decisions', 0);
    }

    public function test_retiring_hides_the_subject_closes_its_reports_and_shows_the_reason_to_its_author(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->published()->for($author, 'author')->create(['title' => 'Commandes SQL piégées']);
        Report::factory()->count(3)->for($subject)->create();

        $this->decide($this->moderator(), $subject, 'retired', 'Réponses dangereuses.')->assertOk();

        $subject->refresh();
        $this->assertSame(SubjectStatus::Retired, $subject->status);
        $this->assertSame('Réponses dangereuses.', $subject->retired_reason);
        $this->assertSame(0, Report::query()->where('status', 'pending')->count());
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/subjects/search', ['search' => []])->assertJsonMissing(['id' => $subject->id]);
        $this->search($author, 'subjects', ['filters' => [['field' => 'author_id', 'value' => $author->id]]])
            ->assertJsonFragment(['status' => 'retired', 'retired_reason' => 'Réponses dangereuses.']);
    }

    public function test_restoring_turns_a_retired_subject_back_into_a_draft(): void
    {
        $subject = Subject::factory()->retired()->create();
        $moderator = $this->moderator();

        $this->decide($moderator, $subject, 'restored')->assertOk();

        $subject->refresh();
        $this->assertSame(SubjectStatus::Draft, $subject->status);
        $this->assertNull($subject->retired_reason);
        $this->decide($moderator, $subject, 'restored')->assertUnprocessable()->assertJson(['code' => 'subject_not_retired']);
    }

    public function test_only_moderators_decide(): void
    {
        $subject = Subject::factory()->published()->create();

        $this->decide(User::factory()->create(), $subject, 'retired', 'Motif')->assertForbidden();
        $this->assertSame(SubjectStatus::Published, $subject->fresh()->status);
    }

    public function test_the_history_keeps_who_when_what_and_why_and_cannot_be_changed(): void
    {
        $moderator = $this->moderator();
        $subject = Subject::factory()->published()->create(['title' => 'Blagues sur les langues']);
        $this->decide($moderator, $subject, 'retired', 'Contenu inapproprié.');
        $decision = ModerationDecision::query()->sole();

        $response = $this->search($moderator, 'moderation-decisions', ['includes' => [['relation' => 'admin']]]);

        $response->assertOk()->assertJsonFragment([
            'subject_title' => 'Blagues sur les langues',
            'decision' => 'retired',
            'reason' => 'Contenu inapproprié.',
            'display_name' => $moderator->display_name,
        ]);
        $this->actingAs($moderator)->postJson('/api/moderation-decisions/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $decision->id, 'attributes' => ['reason' => 'Autre']]],
        ])->assertForbidden();
        $this->actingAs($moderator)->deleteJson('/api/moderation-decisions', ['resources' => [$decision->id]])->assertForbidden();
        $this->search(User::factory()->create(), 'moderation-decisions')->assertForbidden();
    }

    public function test_a_moderator_edits_a_subject_they_did_not_write(): void
    {
        $subject = Subject::factory()->published()->create();

        $this->actingAs($this->moderator())->postJson('/api/subjects/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $subject->id, 'attributes' => ['title' => 'Titre corrigé']]],
        ])->assertOk();

        $this->assertSame('Titre corrigé', $subject->fresh()->title);
    }

    public function test_a_deleted_subject_takes_its_reports_but_keeps_its_history(): void
    {
        $subject = Subject::factory()->published()->create();
        Report::factory()->for($subject)->create();
        $this->decide($this->moderator(), $subject, 'ignored');

        $subject->delete();

        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('moderation_decisions', 1);
    }
}
