<?php

namespace Functional\Learning\Tests\Feature;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\Learning;
use Functional\Learning\Models\ReviewAnswer;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature 006, FR-014 — user story 3: an answer on a card gone meanwhile, paused or someone
 * else's is ignored, and answered like any other so that it reveals nothing (principle VI).
 */
class UnavailableCardAnswersTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    /**
     * @return array<string, array{string}>
     */
    public static function unavailableCards(): array
    {
        return [
            'question deleted' => ['question deleted'],
            'subject no longer learned' => ['learning stopped'],
            'draft subject' => [SubjectStatus::Draft->value],
            'retired subject' => [SubjectStatus::Retired->value],
            'withheld subject' => [SubjectStatus::Withheld->value],
            'card of another account' => ['another account'],
        ];
    }

    #[DataProvider('unavailableCards')]
    public function test_an_answer_on_an_unavailable_card_is_ignored_without_error(string $unavailability): void
    {
        $learner = User::factory()->create();
        $owner = $unavailability === 'another account' ? User::factory()->create() : $learner;
        $card = $this->learnedCard($owner, 2, now()->toDateString());
        $this->makeUnavailable($card, $unavailability);

        $response = $this->answer($learner, $card->id, true, [
            'answer_id' => (string) Str::uuid(),
            'answered_at' => now()->toIso8601String(),
            'due_on' => now()->toDateString(),
        ]);

        $response->assertOk();
        $this->assertSame(0, ReviewAnswer::query()->count());
        $remainingCard = CardProgress::query()->find($card->id);

        if ($remainingCard !== null) {
            $this->assertSame([2, now()->toDateString()], [$remainingCard->box, $remainingCard->next_review_on->toDateString()]);
        }

        $otherLearner = User::factory()->create();
        $answered = $this->answer($otherLearner, $this->learnedCard($otherLearner, 1, now()->toDateString())->id, true)->assertOk();
        $this->assertSame($answered->json(), $response->json());
    }

    private function makeUnavailable(CardProgress $card, string $unavailability): void
    {
        match ($unavailability) {
            'question deleted' => $card->question->delete(),
            'learning stopped' => Learning::query()->findOrFail($card->learning_id)->delete(),
            'another account' => null,
            default => $card->subject->forceFill(['status' => SubjectStatus::from($unavailability)])->save(),
        };
    }
}
