<?php

namespace Functional\Learning\Tests\Feature;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\Learning;
use Functional\Learning\Models\ReviewAnswer;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 004, FR-017 — an erased account takes its learnings, progress and answers along,
 * and leaves the progress of the others untouched.
 */
class EraseLearningsOfUserTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    public function test_the_progress_of_an_erased_account_goes_with_it(): void
    {
        $learner = User::factory()->create();
        $other = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(2);
        $this->learn($learner, $subject)->assertOk();
        $this->learn($other, $subject)->assertOk();
        $card = CardProgress::query()->where('user_id', $learner->id)->firstOrFail();
        $this->answer($learner, $card->id, true)->assertOk();
        $this->app['auth']->forgetGuards();
        $learner->forceFill(['deletion_requested_at' => now()->subDays(31)])->save();

        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

        $this->assertModelMissing($learner);
        $this->assertSame(0, Learning::query()->where('user_id', $learner->id)->count());
        $this->assertSame(0, CardProgress::query()->where('user_id', $learner->id)->count());
        $this->assertSame(0, ReviewAnswer::query()->where('user_id', $learner->id)->count());
        $this->assertSame(2, CardProgress::query()->where('user_id', $other->id)->count());
    }

    public function test_everything_erased_takes_the_progress_of_the_learners_of_the_author_subjects(): void
    {
        [$author, $subject, $learner] = $this->authorLearnedByOthers(keepsPublishedSubjects: false);

        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

        $this->assertModelMissing($subject);
        $this->assertSame(0, Learning::query()->where('user_id', $learner->id)->count());
        $this->assertSame(0, CardProgress::query()->where('user_id', $learner->id)->count());
    }

    public function test_kept_subjects_keep_the_progress_of_their_learners(): void
    {
        [$author, $subject, $learner] = $this->authorLearnedByOthers(keepsPublishedSubjects: true);

        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

        $this->assertModelMissing($author);
        $this->assertSame(1, Learning::query()->where('user_id', $learner->id)->count());
        $this->assertSame(2, CardProgress::query()->where('user_id', $learner->id)->count());
        $this->assertSame(0, Learning::query()->where('subject_id', $subject->id)->where('user_id', $author->id)->count());
    }

    /**
     * An author who learns their own subject, learned by someone else too, 31 days after
     * asking to delete their account.
     *
     * @return array{User, Subject, User}
     */
    private function authorLearnedByOthers(bool $keepsPublishedSubjects): array
    {
        $author = User::factory()->create();
        $learner = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(2);
        $subject->update(['author_id' => $author->id]);
        $this->learn($learner, $subject)->assertOk();
        $this->learn($author, $subject)->assertOk();
        $this->app['auth']->forgetGuards();
        $author->forceFill([
            'deletion_requested_at' => now()->subDays(31),
            'keeps_published_subjects' => $keepsPublishedSubjects,
        ])->save();

        if (! $keepsPublishedSubjects) {
            $subject->forceFill(['status' => SubjectStatus::Withheld])->save();
        }

        return [$author, $subject, $learner];
    }
}
