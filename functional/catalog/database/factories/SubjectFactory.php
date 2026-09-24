<?php

namespace Functional\Catalog\Database\Factories;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Category;
use Functional\Catalog\Models\Subject;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => ucfirst(faker()->words(4)),
            'description' => faker()->sentences(2),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => SubjectStatus::Published,
            'published_at' => faker()->dateTime('-90 days'),
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (): array => [
            'status' => SubjectStatus::Retired,
            'published_at' => faker()->dateTime('-90 days', '-30 days'),
            'retired_reason' => faker()->sentences(1),
            'retired_at' => faker()->dateTime('-30 days'),
        ]);
    }
}
