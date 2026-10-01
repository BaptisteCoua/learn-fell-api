<?php

namespace Functional\Learning\Tests\Feature;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Queries\DueCardsQuery;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 004, FR-009 — the cards of a withheld subject leave the sessions and the counts of its
 * learners, who keep their progress for the day its author changes their mind.
 */
class WithheldSubjectsInReviewsTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    public function test_a_withheld_subject_leaves_the_sessions_and_the_counts(): void
    {
        $learner = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(3);
        $this->learn($learner, $subject)->assertOk();
        $this->assertSame(3, DueCardsQuery::forUser($learner)->count());

        $subject->forceFill(['status' => SubjectStatus::Withheld])->save();

        $this->assertSame(0, DueCardsQuery::forUser($learner)->count());
        $this->assertSame([], collect($this->dueCards($learner, [$subject->id])->assertOk()->json('data'))->all());
        $learnings = $this->actingAs($learner)->postJson('/api/learnings/search', ['search' => []])->assertOk();
        $this->assertSame(0, $learnings->json('data.0.due_today_count'));
        $this->assertSame(3, CardProgress::query()->where('user_id', $learner->id)->count());
    }
}
