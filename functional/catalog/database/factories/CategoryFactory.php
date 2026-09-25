<?php

namespace Functional\Catalog\Database\Factories;

use Functional\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(faker()->unique()->words(2)),
            'position' => faker()->unique()->number(1, 1_000_000),
        ];
    }
}
