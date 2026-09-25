<?php

namespace Functional\Catalog\Database\Factories;

use Functional\Catalog\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => faker()->unique()->words(1),
        ];
    }
}
