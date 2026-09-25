<?php

namespace Functional\Catalog\Database\Seeders;

use Functional\Catalog\Models\Category;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Models\Tag;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

/**
 * Starter catalogue: the six launch categories, and subjects in every status spread
 * across several authors, each with its own questions.
 */
class CatalogSeeder extends Seeder
{
    private const CATEGORIES = ['Langues', 'Histoire', 'Informatique', 'Sciences', 'Géographie', 'Musique'];

    public function run(): void
    {
        $categories = new Collection(array_map(
            fn (string $name, int $index): Category => Category::factory()->create(['name' => $name, 'position' => $index + 1]),
            self::CATEGORIES,
            array_keys(self::CATEGORIES),
        ));

        $authors = User::factory()->count(6)->create();
        $tags = Tag::factory()->count(15)->create();

        foreach ($categories as $category) {
            $subjects = new Collection;

            foreach ([true, true, true, false] as $isPublished) {
                $factory = Subject::factory()->for($category)->for($authors->random(), 'author');
                $subjects->push(($isPublished ? $factory->published() : $factory)->create());
            }

            if ($category->position <= 2) {
                $subjects->push(Subject::factory()->retired()->for($category)->for($authors->random(), 'author')->create());
            }

            foreach ($subjects as $subject) {
                $subject->tags()->attach($tags->random(faker()->number(1, 3))->modelKeys());

                Question::factory()
                    ->count(faker()->number(5, 30))
                    ->state(new Sequence(fn (Sequence $sequence): array => ['position' => $sequence->index + 1]))
                    ->for($subject)
                    ->create();
            }
        }
    }
}
