<?php

namespace Functional\Catalog\Database\Factories;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'recto_html' => '<p>'.faker()->sentences(1).'</p>',
            'verso_html' => '<p>'.faker()->sentences(2).'</p>',
            'position' => faker()->unique()->number(1, 1_000_000),
        ];
    }
}
