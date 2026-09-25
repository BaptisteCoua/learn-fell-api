<?php

namespace Functional\Moderation\Tests\Feature;

use Functional\Catalog\Models\Subject;
use Functional\Moderation\Models\Report;
use Functional\Moderation\Tests\Concerns\Moderates;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-027 to FR-029 — user story 5.
 */
class ReportTest extends TestCase
{
    use Moderates, RefreshDatabase;

    public function test_a_reader_reports_a_published_subject_which_stays_visible(): void
    {
        $reader = User::factory()->create();
        $subject = Subject::factory()->published()->create();

        $this->report($reader, $subject, 'copyright', 'Copié d’une méthode publiée.')->assertOk();

        $report = Report::query()->sole();
        $this->assertSame($reader->id, $report->reporter_id);
        $this->assertSame('pending', $report->status->value);
        $this->postJson('/api/subjects/search', ['search' => []])->assertJsonFragment(['id' => $subject->id]);
    }

    public function test_the_reason_comes_from_the_closed_list_and_the_comment_holds_500_characters(): void
    {
        $reader = User::factory()->create();
        $subject = Subject::factory()->published()->create();

        $this->report($reader, $subject, 'boring')->assertUnprocessable()->assertJsonValidationErrors(['mutate.0.attributes.reason']);
        $this->report($reader, $subject, 'other', str_repeat('a', 501))->assertJsonValidationErrors(['mutate.0.attributes.comment']);
        foreach (['inappropriate', 'incorrect', 'spam', 'copyright', 'other'] as $reason) {
            $this->report(User::factory()->create(), $subject, $reason)->assertOk();
        }
        $this->assertSame(5, Report::query()->count());
    }

    public function test_a_second_pending_report_is_refused_but_a_new_one_after_a_decision_is_not(): void
    {
        $reader = User::factory()->create();
        $subject = Subject::factory()->published()->create();
        $this->report($reader, $subject)->assertOk();

        $this->report($reader, $subject)->assertStatus(409)->assertJson(['code' => 'report_already_pending']);

        $this->decide($this->moderator(), $subject, 'ignored')->assertOk();
        $this->report($reader, $subject)->assertOk();
    }

    public function test_an_author_does_not_report_their_own_subject(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->published()->for($author, 'author')->create();

        $this->report($author, $subject)->assertForbidden();
    }

    public function test_only_a_published_subject_is_reported(): void
    {
        $this->report(User::factory()->create(), Subject::factory()->create())
            ->assertUnprocessable()
            ->assertJson(['code' => 'subject_not_published']);
    }

    public function test_a_visitor_or_an_unconfirmed_account_cannot_report(): void
    {
        $subject = Subject::factory()->published()->create();

        $this->postJson('/api/reports/mutate', ['mutate' => []])->assertUnauthorized();
        $this->report(User::factory()->unverified()->create(), $subject)->assertForbidden();
    }

    public function test_reports_are_read_by_reviewers_only(): void
    {
        $subject = Subject::factory()->published()->create();
        $this->report(User::factory()->create(), $subject);

        $this->search(User::factory()->create(), 'reports')->assertForbidden();
        $this->search($this->moderator(), 'reports')->assertOk()->assertJsonCount(1, 'data');
    }
}
