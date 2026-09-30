<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Catalog\Tests\Concerns\WritesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * FR-014, FR-015 — user story 3.
 */
class QuestionContentTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog, WritesCatalog;

    private function addQuestion(User $author, Subject $subject, string $recto = '<p>Recto</p>', string $verso = '<p>Verso</p>'): TestResponse
    {
        return $this->createResource($author, 'questions', [
            'subject_id' => $subject->id,
            'recto_html' => $recto,
            'verso_html' => $verso,
        ]);
    }

    public function test_the_author_adds_questions_at_the_end_of_the_subject(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();

        $this->addQuestion($author, $subject)->assertOk();
        $this->addQuestion($author, $subject)->assertOk();

        $this->assertSame([1, 2], $subject->questions()->pluck('position')->all());
    }

    public function test_someone_else_cannot_add_a_question_to_a_subject(): void
    {
        $subject = Subject::factory()->published()->create();

        $this->addQuestion(User::factory()->create(), $subject)->assertForbidden();
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_allowed_formatting_is_kept_and_everything_else_is_removed(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();

        $this->addQuestion(
            $author,
            $subject,
            '<p><strong>Quelle</strong> <em>commande</em> ?</p><script>alert(1)</script>',
            '<ul><li onclick="steal()">git switch -c</li></ul><pre><code>git switch -c demo</code></pre>'
                .'<p><a href="javascript:alert(1)">piège</a> <a href="https://git-scm.com">doc</a></p>',
        )->assertOk();

        $question = $subject->questions()->sole();
        $this->assertSame('<p><strong>Quelle</strong> <em>commande</em> ?</p>', $question->recto_html);
        $this->assertStringNotContainsString('onclick', $question->verso_html);
        $this->assertStringNotContainsString('javascript:', $question->verso_html);
        $this->assertStringContainsString('<pre><code>git switch -c demo</code></pre>', $question->verso_html);
        $this->assertStringContainsString('<a rel="noopener nofollow ugc" href="https://git-scm.com">doc</a>', $question->verso_html);
    }

    public function test_recto_and_verso_need_1_to_5000_visible_characters(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();

        $this->addQuestion($author, $subject, '<p> </p>')
            ->assertUnprocessable()
            ->assertJson(['code' => 'recto_empty']);

        $this->addQuestion($author, $subject, '<p>'.str_repeat('a', 5001).'</p>')
            ->assertJsonValidationErrors(['mutate.0.attributes.recto_html']);

        $this->addQuestion($author, $subject, '<p>Recto</p>', '<p> </p>')
            ->assertJsonValidationErrors(['mutate.0.attributes.verso_html']);

        $this->addQuestion($author, $subject, '<p>Recto</p>', '<p>'.str_repeat('a', 5001).'</p>')
            ->assertJsonValidationErrors(['mutate.0.attributes.verso_html']);

        $this->addQuestion($author, $subject, '<p><strong>'.str_repeat('a', 5000).'</strong></p>')->assertOk();
    }

    public function test_a_subject_holds_500_questions_at_most(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();
        Question::factory()->count(Subject::MAX_QUESTIONS)->for($subject)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create();

        $this->addQuestion($author, $subject)->assertUnprocessable()->assertJson(['code' => 'question_limit_reached']);
    }

    public function test_the_author_reorders_the_questions(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();
        $questions = Question::factory()->count(5)->for($subject)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create();
        $newOrder = [$questions[4]->id, $questions[0]->id, $questions[1]->id, $questions[2]->id, $questions[3]->id];

        $this->reorderQuestions($author, $subject->id, $newOrder)->assertOk();

        $this->assertSame($newOrder, $subject->questions()->pluck('id')->all());
    }

    public function test_a_reorder_must_list_exactly_the_questions_of_the_subject(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();
        $questions = Question::factory()->count(2)->for($subject)->sequence(['position' => 1], ['position' => 2])->create();

        $this->reorderQuestions($author, $subject->id, [$questions[0]->id])->assertUnprocessable();
        $this->reorderQuestions(User::factory()->create(), $subject->id, [$questions[1]->id, $questions[0]->id])->assertForbidden();
    }

    public function test_the_images_of_a_question_come_with_it_in_their_order(): void
    {
        Storage::fake(config('catalog.images.disk'));
        $question = Question::factory()->for(Subject::factory()->published())->create(['position' => 1]);
        $second = QuestionImage::factory()->attachedTo($question, 1)->create();
        $first = QuestionImage::factory()->attachedTo($question, 0)->create();

        $response = $this->searchResource('questions', [
            'filters' => [['field' => 'id', 'value' => $question->id]],
            'includes' => [['relation' => 'images']],
        ]);

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], collect($response->json('data.0.images'))->pluck('id')->all());
        $this->assertSame(
            ['id', 'question_id', 'alt', 'position', 'width', 'height', 'variant_widths'],
            array_keys($response->json('data.0.images.0')),
        );
    }

    public function test_images_written_in_rich_text_are_removed(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();

        $this->addQuestion(
            $author,
            $subject,
            '<p>Quel oiseau ? <img src="https://exemple.test/x.png"></p>',
            '<p>Un hibou <img src="https://exemple.test/x.png" alt="x"></p>',
        )->assertOk();

        $question = $subject->questions()->sole();
        $this->assertStringNotContainsString('<img', $question->recto_html);
        $this->assertStringNotContainsString('<img', $question->verso_html);
        $this->assertStringNotContainsString('exemple.test', $question->recto_html.$question->verso_html);
    }

    public function test_editing_a_question_is_visible_at_once(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->published()->for($author, 'author')->create();
        $question = Question::factory()->for($subject)->create(['position' => 1]);

        $this->updateResource($author, 'questions', $question->id, ['verso_html' => '<p>Corrigé</p>'])->assertOk();

        $this->assertSame('<p>Corrigé</p>', $question->fresh()->verso_html);
    }
}
