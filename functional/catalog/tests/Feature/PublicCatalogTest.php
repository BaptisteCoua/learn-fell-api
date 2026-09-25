<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Category;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Models\Tag;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-022, FR-024, FR-026 — a visitor browses a category and reads published subjects.
 */
class PublicCatalogTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog;

    public function test_a_visitor_lists_the_published_subjects_of_a_category_newest_first(): void
    {
        $languages = Category::factory()->create(['name' => 'Langues']);
        $older = Subject::factory()->published()->for($languages)->create(['published_at' => now()->subDays(3)]);
        $newer = Subject::factory()->published()->for($languages)->create(['published_at' => now()->subDay()]);
        Subject::factory()->for($languages)->create();
        Subject::factory()->published()->create();

        $response = $this->searchResource('subjects', [
            'filters' => [['field' => 'category_id', 'value' => $languages->getKey()]],
        ]);

        $response->assertOk();
        $this->assertSame([$newer->getKey(), $older->getKey()], $this->returnedIds($response));
    }

    public function test_a_listed_subject_shows_its_card_details_but_never_the_author_email(): void
    {
        $subject = Subject::factory()->published()->create(['title' => 'Verbes irréguliers anglais']);
        $subject->tags()->attach(Tag::factory()->create(['name' => 'grammaire']));
        Question::factory()->count(3)->for($subject)->sequence(['position' => 1], ['position' => 2], ['position' => 3])->create();

        $response = $this->searchResource('subjects', [
            'includes' => [['relation' => 'author'], ['relation' => 'category'], ['relation' => 'tags']],
            'aggregates' => [['relation' => 'questions', 'type' => 'count']],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Verbes irréguliers anglais')
            ->assertJsonPath('data.0.author.display_name', $subject->author->display_name)
            ->assertJsonPath('data.0.category.name', $subject->category->name)
            ->assertJsonPath('data.0.tags.0.name', 'grammaire')
            ->assertJsonPath('data.0.questions_count', 3)
            ->assertJsonMissingPath('data.0.author.email');
    }

    public function test_results_are_paginated_by_twenty(): void
    {
        Subject::factory()->published()->count(25)->create();

        $response = $this->searchResource('subjects', ['limit' => 20]);

        $response->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 25);
    }

    public function test_a_visitor_reads_every_question_of_a_published_subject_in_order(): void
    {
        $subject = Subject::factory()->published()->create();
        $second = Question::factory()->for($subject)->create(['position' => 2]);
        $first = Question::factory()->for($subject)->create(['position' => 1]);

        $response = $this->searchResource('questions', [
            'filters' => [['field' => 'subject_id', 'value' => $subject->getKey()]],
            'sorts' => [['field' => 'position', 'direction' => 'asc']],
        ]);

        $response->assertOk();
        $this->assertSame([$first->getKey(), $second->getKey()], $this->returnedIds($response));
        $response->assertJsonPath('data.0.recto_html', $first->recto_html);
    }

    public function test_a_visitor_lists_the_categories_in_their_display_order(): void
    {
        $second = Category::factory()->create(['name' => 'Histoire', 'position' => 2]);
        $first = Category::factory()->create(['name' => 'Langues', 'position' => 1]);

        $response = $this->searchResource('categories', ['sorts' => [['field' => 'position', 'direction' => 'asc']]]);

        $response->assertOk();
        $this->assertSame([$first->getKey(), $second->getKey()], $this->returnedIds($response));
    }
}
