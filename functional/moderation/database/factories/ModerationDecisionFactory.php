<?php

namespace Functional\Moderation\Database\Factories;

use Functional\Moderation\Models\ModerationDecision;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModerationDecision>
 */
class ModerationDecisionFactory extends Factory
{
    protected $model = ModerationDecision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => faker()->number(1, 1000),
            'subject_title' => faker()->words(3),
            'admin_id' => User::factory(),
            'decision' => 'ignored',
            'reason' => null,
        ];
    }
}
