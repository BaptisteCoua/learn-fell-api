<?php

namespace Functional\Learning\Database\Factories;

use Functional\Catalog\Models\Subject;
use Functional\Learning\Models\Learning;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Learning>
 */
class LearningFactory extends Factory
{
    protected $model = Learning::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject_id' => Subject::factory()->published(),
        ];
    }
}
