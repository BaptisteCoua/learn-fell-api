<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Category;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Models\Tag;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Catalog\Tests\Concerns\WritesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-011 to FR-014, FR-017, FR-019, FR-020 — user story 3.
 */
class SubjectAuthoringTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog, WritesCatalog;

    public function test_a_member_creates_a_draft_that_only_they_can_see(): void
    {
        $author = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->createResource($author, 'subjects', [
            'title' => 'Verbes irréguliers',
            'description' => 'Les plus courants.',
            'category_id' => $category->id,
        ]);

        $response->assertOk();
        $subject = Subject::query()->findOrFail($response->json('created.0'));
        $this->assertSame(SubjectStatus::Draft, $subject->status);
        $this->assertSame($author->id, $subject->author_id);

        $this->assertSame([$subject->id], $this->returnedIds($this->searchResource('subjects', [], $author)));
        $this->assertSame([], $this->returnedIds($this->searchResource('subjects', [], User::factory()->create())));
        $this->assertSame([], $this->returnedIds($this->searchResource('subjects')));
    }

    public function test_the_status_and_the_author_cannot_be_sent(): void
    {
        $author = User::factory()->create();

        $response = $this->createResource($author, 'subjects', [
            'title' => 'Verbes irréguliers',
            'category_id' => Category::factory()->create()->id,
            'status' => 'published',
            'author_id' => User::factory()->create()->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['mutate.0.attributes.status', 'mutate.0.attributes.author_id']);
    }

    public function test_the_title_needs_3_to_120_characters_and_a_category(): void
    {
        $author = User::factory()->create();

        $this->createResource($author, 'subjects', ['title' => 'ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mutate.0.attributes.title', 'mutate.0.attributes.category_id']);

        $this->createResource($author, 'subjects', [
            'title' => str_repeat('a', 121),
            'category_id' => Category::factory()->create()->id,
        ])->assertJsonValidationErrors(['mutate.0.attributes.title']);
    }

    public function test_the_description_holds_2000_characters_at_most(): void
    {
        $response = $this->createResource(User::factory()->create(), 'subjects', [
            'title' => 'Verbes irréguliers',
            'description' => str_repeat('a', 2001),
            'category_id' => Category::factory()->create()->id,
        ]);

        $response->assertJsonValidationErrors(['mutate.0.attributes.description']);
    }

    public function test_an_unconfirmed_account_cannot_create_a_subject(): void
    {
        $response = $this->createResource(User::factory()->unverified()->create(), 'subjects', [
            'title' => 'Verbes irréguliers',
            'category_id' => Category::factory()->create()->id,
        ]);

        $response->assertForbidden();
    }

    public function test_tags_are_normalized_deduplicated_and_limited_to_10(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();
        $existing = Tag::factory()->create(['name' => 'grammaire']);

        $this->subjectAction($author, 'sync-tags', $subject->id, ['names' => ['Grammaire', '  Verbes   anglais ', 'verbes anglais']])
            ->assertOk();

        $this->assertEqualsCanonicalizing(['grammaire', 'verbes anglais'], $subject->tags()->pluck('name')->all());
        $this->assertTrue($subject->tags()->whereKey($existing->id)->exists());

        $this->subjectAction($author, 'sync-tags', $subject->id, ['names' => array_map(fn (int $index): string => "tag {$index}", range(1, 11))])
            ->assertUnprocessable();
        $this->subjectAction($author, 'sync-tags', $subject->id, ['names' => [str_repeat('a', 31)]])
            ->assertUnprocessable();
    }

    public function test_only_the_author_edits_a_subject_and_readers_see_it_at_once(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->published()->for($author, 'author')->create(['title' => 'Ancien titre']);

        $this->updateResource(User::factory()->create(), 'subjects', $subject->id, ['title' => 'Piraté'])->assertForbidden();
        $this->updateResource($author, 'subjects', $subject->id, ['title' => 'Nouveau titre'])->assertOk();

        $this->assertSame('Nouveau titre', $this->searchResource('subjects')->json('data.0.title'));
    }

    public function test_a_retired_subject_is_read_only_for_its_author(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->retired()->for($author, 'author')->create();

        $this->updateResource($author, 'subjects', $subject->id, ['title' => 'Autre titre'])
            ->assertUnprocessable()
            ->assertJson(['code' => 'subject_retired']);
    }

    public function test_deleting_a_subject_deletes_its_questions_for_good(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->published()->for($author, 'author')->create();
        $subject->tags()->attach(Tag::factory()->create());
        Question::factory()->count(2)->for($subject)->sequence(['position' => 1], ['position' => 2])->create();

        $this->deleteResource(User::factory()->create(), 'subjects', [$subject->id])->assertForbidden();
        $this->deleteResource($author, 'subjects', [$subject->id])->assertOk();

        $this->assertModelMissing($subject);
        $this->assertDatabaseCount('questions', 0);
        $this->assertDatabaseCount('subject_tag', 0);
    }

    public function test_my_subjects_lists_every_status_with_the_retirement_reason(): void
    {
        $author = User::factory()->create();
        $draft = Subject::factory()->for($author, 'author')->create();
        $published = Subject::factory()->published()->for($author, 'author')->create();
        $retired = Subject::factory()->retired()->for($author, 'author')->create(['retired_reason' => 'Contenu recopié.']);
        Subject::factory()->published()->create();

        $response = $this->searchResource('subjects', [
            'filters' => [['field' => 'author_id', 'value' => $author->id]],
        ], $author);

        $this->assertEqualsCanonicalizing([$draft->id, $published->id, $retired->id], $this->returnedIds($response));
        $this->assertContains('Contenu recopié.', collect($response->json('data'))->pluck('retired_reason'));
    }
}
