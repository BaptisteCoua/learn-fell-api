<?php

namespace Functional\Learning\Tests\Feature;

use Functional\Learning\Domain\LeitnerSchedule;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * specs/008-question-import — FR-020, US4: an imported question reaches the learners of its
 * subject exactly as a question written by hand (feature 001, FR-051).
 */
class ImportedQuestionsReachLearnersTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    private function pastedQuestions(int $count, string $verso = 'Verso'): string
    {
        return implode("\n", array_map(fn (int $index): string => "Question importée {$index}\t{$verso} {$index}", range(1, $count)));
    }

    public function test_imported_questions_enter_box_1_for_every_learner_today_in_their_timezone(): void
    {
        $this->travelTo('2026-10-02 22:30:00');
        $subject = $this->publishedSubjectWithQuestions(2);
        $kiritimati = User::factory()->create(['timezone' => 'Pacific/Kiritimati']);
        $losAngeles = User::factory()->create(['timezone' => 'America/Los_Angeles']);
        $this->learn($kiritimati, $subject)->assertOk();
        $this->learn($losAngeles, $subject)->assertOk();
        CardProgress::query()->update(['box' => 3, 'next_review_on' => '2026-10-06']);

        $this->actingAs($subject->author)->postJson("/api/subjects/{$subject->id}/question-import", [
            'text' => $this->pastedQuestions(30),
            'import_id' => (string) Str::uuid(),
        ])->assertCreated();

        foreach ([$kiritimati, $losAngeles] as $learner) {
            $newCards = CardProgress::query()->where('user_id', $learner->id)->where('box', LeitnerSchedule::FIRST_BOX)->get();
            $this->assertCount(30, $newCards);
            $this->assertSame(
                [LeitnerSchedule::todayFor($learner->timezone)->toDateString()],
                $newCards->map(fn (CardProgress $card): string => $card->next_review_on->toDateString())->unique()->values()->all(),
            );
            $this->assertSame(2, CardProgress::query()->where('user_id', $learner->id)->where('box', 3)->where('next_review_on', '2026-10-06')->count());
        }

        $this->assertNotSame(
            LeitnerSchedule::todayFor('Pacific/Kiritimati')->toDateString(),
            LeitnerSchedule::todayFor('America/Los_Angeles')->toDateString(),
        );
    }

    public function test_a_refused_import_gives_nobody_a_card(): void
    {
        $subject = $this->publishedSubjectWithQuestions(2);
        $this->learn(User::factory()->create(), $subject)->assertOk();

        $this->actingAs($subject->author)->postJson("/api/subjects/{$subject->id}/question-import", [
            'text' => $this->pastedQuestions(5)."\nQuestion sans verso\t",
            'import_id' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJson(['code' => 'import_has_errors']);

        $this->assertSame(2, CardProgress::query()->count());
    }
}
