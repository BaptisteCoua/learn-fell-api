<?php

namespace Functional\Moderation\Database\Factories;

use Functional\Catalog\Models\Subject;
use Functional\Moderation\Models\Report;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory()->published(),
            'reporter_id' => User::factory(),
            'reason' => 'incorrect',
            'comment' => faker()->sentences(1),
            'status' => 'pending',
        ];
    }
}
