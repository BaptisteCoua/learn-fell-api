<?php

namespace Functional\Learning\Tests\Feature;

use Functional\Catalog\Models\Subject;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * specs/008-question-import — FR-019, research R8: whether someone learns a subject, for the
 * warning its author reads before importing questions into it. Neither a count nor a name.
 */
class SubjectLearnersTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    private function hasLearners(?User $user, Subject $subject): TestResponse
    {
        if ($user === null) {
            $this->app['auth']->forgetGuards();
        } else {
            $this->actingAs($user);
        }

        return $this->getJson("/api/learning/subjects/{$subject->id}/learners");
    }

    public function test_a_subject_someone_learns_has_learners(): void
    {
        $subject = $this->publishedSubjectWithQuestions(2);
        $this->learn(User::factory()->create(), $subject)->assertOk();

        $this->hasLearners($subject->author, $subject)->assertOk()->assertExactJson(['has_learners' => true]);
    }

    public function test_an_author_learning_their_own_subject_is_a_learner(): void
    {
        $subject = $this->publishedSubjectWithQuestions(2);
        $this->learn($subject->author, $subject)->assertOk();

        $this->hasLearners($subject->author, $subject)->assertExactJson(['has_learners' => true]);
    }

    public function test_a_subject_nobody_learns_has_no_learners(): void
    {
        $subject = $this->publishedSubjectWithQuestions(2);

        $this->hasLearners($subject->author, $subject)->assertExactJson(['has_learners' => false]);
    }

    public function test_an_administrator_may_ask_for_any_subject(): void
    {
        $subject = $this->publishedSubjectWithQuestions(1);

        $this->hasLearners(User::factory()->create()->assignRole('admin'), $subject)->assertOk();
    }

    public function test_nobody_else_may_ask(): void
    {
        $subject = $this->publishedSubjectWithQuestions(1);
        $this->learn(User::factory()->create(), $subject)->assertOk();

        $this->assertContains($this->hasLearners(User::factory()->create(), $subject)->status(), [403, 404]);
        $this->hasLearners(null, $subject)->assertUnauthorized();
    }
}
