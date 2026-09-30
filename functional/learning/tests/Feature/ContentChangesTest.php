<?php

namespace Functional\Learning\Tests\Feature;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_changing_the_images_of_a_question_keeps_its_box_and_due_date(): void
    {
        Storage::fake(config('catalog.images.disk'));
        $learner = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(1);
        $this->learn($learner, $subject);
        $question = $subject->questions()->sole();
        $card = CardProgress::query()->where('question_id', $question->id)->sole();
        $card->update(['box' => 3, 'next_review_on' => now()->addDays(4)->toDateString()]);
        [$first, $second, $replacement] = QuestionImage::factory()->count(3)->pending()->for($subject->author, 'uploader')->create();

        $steps = [
            'add' => [$this->keptImage($first, 0), $this->keptImage($second, 1)],
            'replace' => [$this->keptImage($first, 0), $this->keptImage($replacement, 1)],
            'reorder' => [$this->keptImage($replacement, 0), $this->keptImage($first, 1)],
            'remove' => [$this->keptImage($replacement, 0)],
        ];

        foreach ($steps as $images) {
            $this->actingAs($subject->author)->postJson('/api/questions/mutate', [
                'mutate' => [['operation' => 'update', 'key' => $question->id, 'attributes' => [], 'relations' => ['images' => $images]]],
            ])->assertOk();

            $card->refresh();
            $this->assertSame(3, $card->box);
            $this->assertSame(now()->addDays(4)->toDateString(), $card->next_review_on->toDateString());
        }

        $this->assertSame([$replacement->id], $question->images()->pluck('id')->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function keptImage(QuestionImage $image, int $position): array
    {
        return ['operation' => 'update', 'key' => $image->id, 'attributes' => ['alt' => 'Une photo du sujet', 'position' => $position]];
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
