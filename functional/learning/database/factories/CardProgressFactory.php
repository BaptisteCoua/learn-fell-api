<?php

namespace Functional\Learning\Database\Factories;

use Functional\Learning\Models\CardProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Build it from a learning: `CardProgress::factory()->for($learning)->for($question)`.
 *
 * @extends Factory<CardProgress>
 */
class CardProgressFactory extends Factory
{
    protected $model = CardProgress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'box' => 1,
            'next_review_on' => now()->toDateString(),
        ];
    }
}
