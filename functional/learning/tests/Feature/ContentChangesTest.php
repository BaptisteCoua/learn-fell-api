<?php

namespace Functional\Learning\Tests\Feature;

use Functional\Catalog\Models\Question;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-051, FR-019 — the review follows the content of the subject.
 */
class ContentChangesTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    public function test_a_new_question_reaches_every_learner_in_box_1(): void
    {
        $subject = $this->publishedSubjectWithQuestions(1);
        [$ines, $hugo] = User::factory()->count(2)->create();
        $this->learn($ines, $subject);
        $this->learn($hugo, $subject);

        $question = Question::factory()->for($subject)->create(['position' => 2]);

        $this->assertSame(2, CardProgress::query()->where('question_id', $question->id)->where('box', 1)->count());
    }

    public function test_a_deleted_question_leaves_the_review(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(2);
        $this->learn($user, $subject);
        $question = $subject->questions()->first();
        $card = CardProgress::query()->where('question_id', $question->id)->sole();
        $this->answer($user, $card->id, true);

        $question->delete();

        $this->assertModelMissing($question);
        $this->assertSame(1, CardProgress::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseCount('review_answers', 0);
    }

    public function test_a_deleted_subject_takes_its_learnings_with_it(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(2);
        $this->learn($user, $subject);

        $subject->delete();

        $this->assertModelMissing($subject);
        $this->assertDatabaseCount('learnings', 0);
        $this->assertDatabaseCount('card_progress', 0);
    }
}
