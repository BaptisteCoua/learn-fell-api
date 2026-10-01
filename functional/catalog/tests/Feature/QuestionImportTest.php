<?php

namespace Functional\Catalog\Tests\Feature;

use DOMDocument;
use Functional\Catalog\Events\QuestionCreated;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\MakesImportSources;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Catalog\Tests\Concerns\WritesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * specs/008-question-import — FR-014 to FR-018, FR-027: confirming an import adds every question
 * at the end of the subject, once.
 */
class QuestionImportTest extends TestCase
{
    use MakesImportSources, RefreshDatabase, SearchesCatalog, WritesCatalog;

    private User $author;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
        $this->subject = Subject::factory()->for($this->author, 'author')->create();
        Question::factory()->count(3)->for($this->subject)
            ->sequence(fn ($sequence) => ['position' => $sequence->index + 1])
            ->create();
    }

    public function test_every_question_goes_at_the_end_of_the_subject_in_the_order_of_the_file(): void
    {
        $firstThree = $this->subject->questions()->get(['id', 'recto_html', 'position'])->toArray();
        $importId = (string) Str::uuid();

        $this->confirmImport($this->author, $this->subject, ['file' => $this->referenceXlsx(), 'import_id' => $importId])
            ->assertCreated()
            ->assertExactJson(['data' => ['imported' => 50]]);

        $questions = $this->subject->questions()->get();
        $this->assertSame(range(1, 53), $questions->pluck('position')->all());
        $this->assertSame($firstThree, $questions->take(3)->map->only(['id', 'recto_html', 'position'])->values()->all());
        $this->assertSame('<p>Question 2 : où bat le cœur de « Lima » ?</p>', $questions[4]->recto_html);
        $this->assertSame('<p>Question 50 : où bat le cœur de « Lima » ?</p>', $questions[52]->recto_html);
        $this->assertSame(50, Question::query()->where('import_id', $importId)->count());
    }

    public function test_a_confirmation_sent_twice_adds_the_questions_once(): void
    {
        $payload = fn (): array => ['file' => $this->referenceXlsx(), 'import_id' => (string) Str::uuid()];
        $first = $payload();

        $this->confirmImport($this->author, $this->subject, $first)->assertCreated();
        $this->confirmImport($this->author, $this->subject, [...$first, 'file' => $this->referenceXlsx()])
            ->assertOk()
            ->assertExactJson(['data' => ['imported' => 50]]);

        $this->assertSame(53, $this->subject->questions()->count());
    }

    public function test_the_confirmation_needs_an_import_id(): void
    {
        $this->confirmImport($this->author, $this->subject, ['file' => $this->referenceXlsx()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('import_id');
        $this->confirmImport($this->author, $this->subject, ['file' => $this->referenceXlsx(), 'import_id' => 'pas-un-uuid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('import_id');

        $this->assertSame(3, $this->subject->questions()->count());
    }

    public function test_an_imported_question_is_an_ordinary_question(): void
    {
        $this->confirmImport($this->author, $this->subject, ['file' => $this->referenceXlsx(), 'import_id' => (string) Str::uuid()])
            ->assertCreated();
        $imported = $this->subject->questions()->where('position', 4)->sole();

        $this->updateResource($this->author, 'questions', $imported->id, ['verso_html' => '<p>Lima, au Pérou</p>'])->assertOk();
        $this->reorderQuestions($this->author, $this->subject->id, [
            $imported->id,
            ...$this->subject->questions()->whereKeyNot($imported->id)->pluck('id')->all(),
        ])->assertOk();
        $this->assertSame(1, $imported->refresh()->position);
        $this->assertSame('<p>Lima, au Pérou</p>', $imported->verso_html);

        $this->deleteResource($this->author, 'questions', [$imported->id])->assertOk();
        $this->assertModelMissing($imported);
    }

    public function test_the_import_id_is_never_shown(): void
    {
        $this->confirmImport($this->author, $this->subject, ['file' => $this->referenceXlsx(), 'import_id' => (string) Str::uuid()])
            ->assertCreated();

        $response = $this->searchResource('questions', ['filters' => [['field' => 'subject_id', 'value' => $this->subject->id]]], $this->author);

        $response->assertOk();
        $this->assertArrayNotHasKey('import_id', $response->json('data.4'));
    }

    public function test_a_pasted_text_is_imported_the_same_way(): void
    {
        $this->confirmImport($this->author, $this->subject, ['text' => "Pérou\tLima\nChili\tSantiago", 'import_id' => (string) Str::uuid()])
            ->assertCreated()
            ->assertExactJson(['data' => ['imported' => 2]]);

        $this->assertSame(['<p>Pérou</p>', '<p>Chili</p>'], $this->subject->questions()->where('position', '>', 3)->pluck('recto_html')->all());
    }

    public function test_a_source_with_a_line_to_fix_imports_nothing(): void
    {
        $file = $this->xlsxFile([['Pérou', 'Lima'], ['Chili', '']]);

        $this->confirmImport($this->author, $this->subject, ['file' => $file, 'import_id' => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJson([
                'code' => 'import_has_errors',
                'message' => 'Certaines lignes sont à corriger : rien n’a été importé. Corrigez-les, puis envoyez de nouveau votre source.',
            ]);

        $this->assertSame(3, $this->subject->questions()->count());
    }

    public function test_a_limit_reached_after_the_preview_imports_nothing(): void
    {
        $this->previewImport($this->author, $this->subject, ['file' => $this->referenceXlsx()])
            ->assertJsonPath('data.can_confirm', true);
        Question::factory()->count(460)->for($this->subject)->sequence(fn ($sequence) => ['position' => $sequence->index + 4])->create();

        $this->confirmImport($this->author, $this->subject, ['file' => $this->referenceXlsx(), 'import_id' => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJson(['code' => 'import_has_errors']);

        $this->assertSame(463, $this->subject->questions()->count());
    }

    public function test_a_subject_retired_after_the_preview_receives_nothing(): void
    {
        $this->subject->forceFill(['status' => 'retired', 'retired_reason' => 'Spam', 'retired_at' => now()])->save();

        $this->confirmImport($this->author, $this->subject, ['file' => $this->referenceXlsx(), 'import_id' => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJson(['code' => 'subject_retired']);

        $this->assertSame(3, $this->subject->questions()->count());
    }

    public function test_a_failure_in_the_middle_of_the_import_leaves_the_subject_as_it_was(): void
    {
        $created = 0;
        Event::listen(QuestionCreated::class, function () use (&$created): void {
            if (++$created === 30) {
                throw new RuntimeException('The database went away.');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->confirmImport($this->author, $this->subject, ['file' => $this->referenceXlsx(), 'import_id' => (string) Str::uuid()]);
            $this->fail('The import went through.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The database went away.', $exception->getMessage());
        }

        $this->assertSame(3, $this->subject->questions()->count());
        $this->assertSame([1, 2, 3], $this->subject->questions()->pluck('position')->all());
    }

    public function test_nothing_imported_can_run_in_the_browser_of_a_reader(): void
    {
        $file = $this->xlsxFile([
            ['<script>alert(1)</script>', '<img src=x onerror=alert(1)>'],
            ['[cliquez](javascript:alert(1))', '<a href="javascript:alert(1)">piège</a>'],
            ['<https://example.org/" onmouseover="alert(1)>', '[x](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)'],
        ]);

        $this->confirmImport($this->author, $this->subject, ['file' => $file, 'import_id' => (string) Str::uuid()])
            ->assertCreated();

        foreach ($this->subject->questions()->where('position', '>', 3)->get() as $question) {
            foreach ([$question->recto_html, $question->verso_html] as $html) {
                $this->assertOnlyHarmlessMarkup($html);
            }
        }
    }

    /**
     * What a browser would run: an element outside the editor's formatting, an attribute other
     * than a link's, or a link that is not http(s) or mailto. Escaped text runs nothing.
     */
    private function assertOnlyHarmlessMarkup(string $html): void
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NOERROR);

        foreach ($document->getElementsByTagName('body')->item(0)->getElementsByTagName('*') as $element) {
            $this->assertContains($element->tagName, ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'code', 'pre', 'a'], $html);

            foreach ($element->attributes as $attribute) {
                $this->assertContains($attribute->name, ['href', 'rel'], $html);
            }

            if ($element->hasAttribute('href')) {
                $this->assertMatchesRegularExpression('#^(https?:|mailto:)#', $element->getAttribute('href'), $html);
            }
        }
    }

    public function test_the_preview_shows_exactly_what_is_saved(): void
    {
        $preview = $this->previewImport($this->author, $this->subject, ['file' => $this->referenceXlsx()])->assertOk();

        $this->confirmImport($this->author, $this->subject, ['file' => $this->referenceXlsx(), 'import_id' => (string) Str::uuid()])
            ->assertCreated();

        $saved = $this->subject->questions()->where('position', '>', 3)->get(['recto_html', 'verso_html']);
        $this->assertSame(
            collect($preview->json('data.rows'))->map(fn (array $row): array => [$row['recto_html'], $row['verso_html']])->all(),
            $saved->map(fn (Question $question): array => [$question->recto_html, $question->verso_html])->all(),
        );
    }
}
