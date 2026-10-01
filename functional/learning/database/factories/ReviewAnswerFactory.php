<?php

namespace Functional\Learning\Database\Factories;

use Carbon\CarbonImmutable;
use Functional\Learning\Domain\LeitnerSchedule;
use Functional\Learning\Enums\AnswerStatus;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\ReviewAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Build it from a card: `ReviewAnswer::factory()->forCard($card)`, which gives it the card's
 * learner. It answered the due date of the day it was given.
 *
 * @extends Factory<ReviewAnswer>
 */
class ReviewAnswerFactory extends Factory
{
    protected $model = ReviewAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'answer_id' => faker()->uuid(),
            'known' => faker()->boolean(),
            'from_box' => 1,
            'to_box' => fn (array $attributes): int => LeitnerSchedule::arrivalBox($attributes['from_box'], $attributes['known']),
            'answered_at' => now(),
            'due_on' => fn (array $attributes): string => CarbonImmutable::parse($attributes['answered_at'])->toDateString(),
            'status' => AnswerStatus::Applied,
        ];
    }

    public function forCard(CardProgress $card): static
    {
        return $this->state([
            'card_progress_id' => $card->getKey(),
            'user_id' => $card->user_id,
        ]);
    }

    public function discarded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'to_box' => $attributes['from_box'],
            'status' => AnswerStatus::Discarded,
        ]);
    }
}
