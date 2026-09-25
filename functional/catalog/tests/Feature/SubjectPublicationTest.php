<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Catalog\Tests\Concerns\WritesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-016 to FR-018, FR-033 — user story 3.
 */
class SubjectPublicationTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog, WritesCatalog;

    public function test_a_draft_with_a_question_is_published_and_reaches_the_catalogue(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();
        Question::factory()->for($subject)->create(['position' => 1]);

        $this->subjectAction($author, 'publish', $subject->id)->assertOk();

        $subject->refresh();
        $this->assertSame(SubjectStatus::Published, $subject->status);
        $this->assertNotNull($subject->published_at);
        $this->assertSame([$subject->id], $this->returnedIds($this->searchResource('subjects')));
    }

    public function test_a_draft_without_question_cannot_be_published(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();

        $this->subjectAction($author, 'publish', $subject->id)
            ->assertUnprocessable()
            ->assertJson(['code' => 'subject_has_no_question']);

        $this->assertSame(SubjectStatus::Draft, $subject->fresh()->status);
    }

    public function test_a_retired_subject_cannot_be_published_by_its_author(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->retired()->for($author, 'author')->create();
        Question::factory()->for($subject)->create(['position' => 1]);

        $this->subjectAction($author, 'publish', $subject->id)
            ->assertUnprocessable()
            ->assertJson(['code' => 'subject_retired']);
    }

    public function test_someone_else_cannot_publish_a_subject(): void
    {
        $subject = Subject::factory()->published()->create();

        $this->subjectAction(User::factory()->create(), 'unpublish', $subject->id)->assertForbidden();
        $this->assertSame(SubjectStatus::Published, $subject->fresh()->status);
    }

    public function test_unpublishing_turns_the_subject_back_into_a_draft_hidden_from_readers(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->published()->for($author, 'author')->create();

        $this->subjectAction($author, 'unpublish', $subject->id)->assertOk();

        $this->assertSame(SubjectStatus::Draft, $subject->fresh()->status);
        $this->assertSame([], $this->returnedIds($this->searchResource('subjects')));
        $this->assertSame([$subject->id], $this->returnedIds($this->searchResource('subjects', [], $author)));
    }

    public function test_the_last_question_of_a_published_subject_cannot_be_deleted(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->published()->for($author, 'author')->create();
        [$first, $last] = Question::factory()->count(2)->for($subject)->sequence(['position' => 1], ['position' => 2])->create();

        $this->deleteResource($author, 'questions', [$first->id])->assertOk();
        $this->deleteResource($author, 'questions', [$last->id])
            ->assertUnprocessable()
            ->assertJson(['code' => 'last_question_of_published_subject']);

        $this->assertModelExists($last);
    }
}
