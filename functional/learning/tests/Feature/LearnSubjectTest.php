<?php

namespace Functional\Learning\Tests\Feature;

use Functional\Catalog\Models\Subject;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\Learning;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-041, FR-043, FR-050 — user story 6.
 */
class LearnSubjectTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    public function test_learning_a_subject_puts_every_question_in_box_1_due_today(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $subject = $this->publishedSubjectWithQuestions(3);

        $this->learn($user, $subject)->assertOk();

        $cards = CardProgress::query()->where('user_id', $user->id)->get();
        $this->assertCount(3, $cards);
        $this->assertSame([1], $cards->pluck('box')->unique()->values()->all());
        $this->assertSame([now('Europe/Paris')->toDateString()], $cards->map(fn ($card) => $card->next_review_on->toDateString())->unique()->values()->all());
    }

    public function test_a_subject_is_learned_once(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(1);
        $this->learn($user, $subject)->assertOk();

        $this->learn($user, $subject)->assertStatus(409)->assertJson(['code' => 'already_learning']);
    }

    public function test_only_a_published_subject_can_be_learned(): void
    {
        $user = User::factory()->create();
        $draft = Subject::factory()->for($user, 'author')->create();

        $this->learn($user, $draft)->assertUnprocessable()->assertJson(['code' => 'subject_not_published']);
    }

    public function test_a_visitor_cannot_learn(): void
    {
        $this->postJson('/api/learnings/search')->assertUnauthorized();
    }

    public function test_the_learnings_count_their_cards_and_belong_to_their_learner(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(3);
        $this->learn($user, $subject);
        $this->learn(User::factory()->create(), $subject);
        CardProgress::query()->where('user_id', $user->id)->limit(1)->update(['box' => 3, 'next_review_on' => now()->addDays(4)->toDateString()]);

        $response = $this->actingAs($user)->postJson('/api/learnings/search', ['search' => []]);

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(2, $response->json('data.0.due_today_count'));
        $this->assertSame(2, $response->json('data.0.box_1_count'));
        $this->assertSame(1, $response->json('data.0.box_3_count'));
        $this->assertSame(now()->toDateString(), substr((string) $response->json('data.0.next_review_on'), 0, 10));
    }

    public function test_stopping_to_learn_forgets_the_cards_and_answers(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(2);
        $this->learn($user, $subject);
        $card = CardProgress::query()->where('user_id', $user->id)->firstOrFail();
        $this->answer($user, $card->id, true)->assertOk();
        $learning = Learning::query()->where('user_id', $user->id)->sole();

        $this->actingAs($user)->deleteJson('/api/learnings', ['resources' => [$learning->id]])->assertOk();

        $this->assertDatabaseCount('learnings', 0);
        $this->assertDatabaseCount('card_progress', 0);
        $this->assertDatabaseCount('review_answers', 0);
    }

    public function test_someone_else_cannot_stop_my_learning(): void
    {
        $user = User::factory()->create();
        $subject = $this->publishedSubjectWithQuestions(1);
        $this->learn($user, $subject);
        $learning = Learning::query()->sole();

        $this->actingAs(User::factory()->create())->deleteJson('/api/learnings', ['resources' => [$learning->id]])->assertForbidden();
        $this->assertModelExists($learning);
    }
}
