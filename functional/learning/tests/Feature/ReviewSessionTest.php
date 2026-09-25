<?php

namespace Functional\Learning\Tests\Feature;

use Functional\Catalog\Models\Question;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\ReviewAnswer;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * FR-042 to FR-049 — user story 6.
 */
class ReviewSessionTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    /**
     * @return list<int>
     */
    private function returnedIds(TestResponse $response): array
    {
        return collect($response->json('data'))->pluck('id')->all();
    }

    public function test_the_due_cards_are_those_of_the_chosen_subjects_most_overdue_first(): void
    {
        $user = User::factory()->create();
        $chosen = $this->publishedSubjectWithQuestions(3);
        $other = $this->publishedSubjectWithQuestions(2);
        $this->learn($user, $chosen);
        $this->learn($user, $other);
        [$first, $second, $third] = CardProgress::query()->where('subject_id', $chosen->id)->orderBy('id')->get();
        $third->update(['next_review_on' => now()->subDays(3)->toDateString()]);
        $second->update(['next_review_on' => now()->addDay()->toDateString()]);

        $response = $this->dueCards($user, [$chosen->id]);

        $response->assertOk();
        $this->assertSame([$third->id, $first->id], $this->returnedIds($response));
    }

    public function test_the_cards_of_a_subject_come_in_its_question_order(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(3);
        $this->learn($user, $subject);
        $questions = $subject->questions()->get();
        $questions[0]->update(['position' => 10]);

        $response = $this->actingAs($user)->postJson('/api/card-progress/search', [
            'search' => [
                'instructions' => [['name' => 'due', 'fields' => [['name' => 'subject_ids', 'value' => [$subject->id]]]]],
                'includes' => [['relation' => 'question']],
            ],
        ]);

        $this->assertSame(
            [$questions[1]->id, $questions[2]->id, $questions[0]->id],
            collect($response->json('data'))->pluck('question_id')->all(),
        );
        $this->assertNotEmpty($response->json('data.0.question.recto_html'));
    }

    public function test_an_unpublished_subject_pauses_its_cards(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(2);
        $this->learn($user, $subject);

        $subject->forceFill(['status' => 'draft'])->save();
        $this->assertSame([], $this->returnedIds($this->dueCards($user, [$subject->id])));

        $subject->forceFill(['status' => 'published'])->save();
        $this->assertCount(2, $this->returnedIds($this->dueCards($user, [$subject->id])));
    }

    public function test_an_answer_applies_the_leitner_rule_and_is_recorded(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $subject = $this->publishedSubjectWithQuestions(5);
        $this->learn($user, $subject);
        $cards = CardProgress::query()->where('user_id', $user->id)->orderBy('id')->get();

        foreach ($cards as $index => $card) {
            $this->answer($user, $card->id, $index < 3)->assertOk();
        }

        $today = now('Europe/Paris');
        $boxes = CardProgress::query()->where('user_id', $user->id)->orderBy('id')->get();
        $this->assertSame([2, 2, 2, 1, 1], $boxes->pluck('box')->all());
        $this->assertSame(
            array_merge(array_fill(0, 3, $today->copy()->addDays(2)->toDateString()), array_fill(0, 2, $today->copy()->addDay()->toDateString())),
            $boxes->map(fn ($card) => $card->next_review_on->toDateString())->all(),
        );
        $this->assertSame(3, ReviewAnswer::query()->where('known', true)->count());
        $this->assertSame([1], ReviewAnswer::query()->pluck('from_box')->unique()->values()->all());
    }

    public function test_a_second_answer_on_the_same_card_is_refused(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(1);
        $this->learn($user, $subject);
        $card = CardProgress::query()->sole();

        $this->answer($user, $card->id, true)->assertOk();
        $this->answer($user, $card->id, false)->assertStatus(409)->assertJson(['code' => 'card_not_due']);

        $this->assertSame(2, $card->fresh()->box);
        $this->assertSame(1, ReviewAnswer::query()->count());
    }

    public function test_nobody_answers_someone_elses_card(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(1);
        $this->learn($user, $subject);
        $card = CardProgress::query()->sole();

        $this->answer(User::factory()->create(), $card->id, true)->assertNotFound();
        $this->assertSame([], $this->returnedIds($this->dueCards(User::factory()->create(), [$subject->id])));
    }

    public function test_cards_cannot_be_written_directly(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(1);
        $this->learn($user, $subject);
        $card = CardProgress::query()->sole();

        $this->actingAs($user)->postJson('/api/card-progress/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $card->id, 'attributes' => ['box' => 5]]],
        ])->assertForbidden();
    }

    public function test_the_card_shows_where_it_went_after_an_answer(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(1);
        $this->learn($user, $subject);
        $card = CardProgress::query()->sole();
        $card->update(['box' => 4]);

        $this->answer($user, $card->id, true)->assertOk();

        $response = $this->actingAs($user)->postJson('/api/card-progress/search', [
            'search' => ['filters' => [['field' => 'id', 'value' => $card->id]]],
        ]);
        $this->assertSame(5, $response->json('data.0.box'));
        $this->assertSame(now()->addDays(16)->toDateString(), substr((string) $response->json('data.0.next_review_on'), 0, 10));
    }

    public function test_questions_of_a_new_learning_follow_the_subject_order(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(2);
        Question::query()->where('subject_id', $subject->id)->get();

        $this->learn($user, $subject);

        $this->assertCount(2, $this->returnedIds($this->dueCards($user, [$subject->id])));
    }
}
