<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\MakesImportSources;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * specs/008-question-import — FR-001, FR-002, FR-009, FR-010, FR-013: who may preview an
 * import, and what the preview of a file shows.
 */
class QuestionImportPreviewTest extends TestCase
{
    use MakesImportSources, RefreshDatabase;

    private function draftOf(User $author): Subject
    {
        return Subject::factory()->for($author, 'author')->create();
    }

    public function test_the_author_sees_every_question_of_the_file_before_anything_is_saved(): void
    {
        $author = User::factory()->create();
        $subject = $this->draftOf($author);
        Question::factory()->count(3)->for($subject)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create();

        $response = $this->previewImport($author, $subject, ['file' => $this->referenceXlsx()]);

        $response->assertOk()
            ->assertJsonPath('data.question_count', 50)
            ->assertJsonPath('data.error_line_count', 0)
            ->assertJsonPath('data.can_confirm', true)
            ->assertJsonPath('data.errors', [])
            ->assertJsonPath('data.notices', [[
                'code' => 'header_ignored',
                'message' => 'La première ligne est un en-tête : elle n’est pas importée.',
            ]])
            ->assertJsonCount(50, 'data.rows')
            ->assertJsonPath('data.rows.1', [
                'line' => 3,
                'recto_html' => '<p>Question 2 : où bat le cœur de « Lima » ?</p>',
                'verso_html' => '<p>Réponse 2 : à 5 € l’entrée</p>',
                'errors' => [],
                'warnings' => [],
            ]);
        $this->assertMatchesRegularExpression('#^<p>Lima<br ?/?>\s*la « ville des rois »</p>$#', $response->json('data.rows.0.verso_html'));
        $this->assertSame(3, $subject->questions()->count());
    }

    public function test_an_administrator_previews_an_import_into_any_subject_even_retired(): void
    {
        $subject = Subject::factory()->retired()->create();
        $admin = User::factory()->create()->assignRole('admin');

        $this->previewImport($admin, $subject, ['file' => $this->referenceXlsx()])->assertOk();
    }

    public function test_someone_else_cannot_preview_an_import_into_a_subject(): void
    {
        $subject = Subject::factory()->published()->create();

        $status = $this->previewImport(User::factory()->create(), $subject, ['file' => $this->referenceXlsx()])->status();

        $this->assertContains($status, [403, 404]);
    }

    public function test_the_author_of_a_retired_subject_cannot_import_into_it(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->retired()->for($author, 'author')->create();

        $this->previewImport($author, $subject, ['file' => $this->referenceXlsx()])
            ->assertUnprocessable()
            ->assertJson(['code' => 'subject_retired']);
    }

    public function test_a_visitor_cannot_preview_an_import(): void
    {
        $this->previewImport(null, Subject::factory()->create(), ['file' => $this->referenceXlsx()])->assertUnauthorized();
    }

    public function test_an_account_whose_email_is_not_confirmed_cannot_preview_an_import(): void
    {
        $author = User::factory()->unverified()->create();

        $this->previewImport($author, $this->draftOf($author), ['file' => $this->referenceXlsx()])->assertForbidden();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableFiles(): array
    {
        return [
            'pdf' => ['pdf'],
            'corrupt workbook' => ['corrupt'],
        ];
    }

    #[DataProvider('unreadableFiles')]
    public function test_a_file_that_is_not_a_readable_spreadsheet_is_refused(string $kind): void
    {
        $author = User::factory()->create();
        $file = $kind === 'pdf' ? $this->importFixture('not-a-sheet.pdf') : $this->corruptXlsx();

        $this->previewImport($author, $this->draftOf($author), ['file' => $file])
            ->assertUnprocessable()
            ->assertJson([
                'code' => 'import_unreadable',
                'message' => 'Ce fichier ne peut pas être lu. Envoyez un fichier CSV ou XLSX, par exemple enregistré depuis votre tableur.',
            ]);
    }

    public function test_a_file_over_5_megabytes_is_refused(): void
    {
        $author = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('questions.csv', str_repeat("Pérou;Lima\n", 500_000));

        $this->previewImport($author, $this->draftOf($author), ['file' => $file])
            ->assertUnprocessable()
            ->assertJson(['code' => 'import_too_large']);
    }

    public function test_a_file_of_more_than_2000_lines_is_refused(): void
    {
        $author = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('questions.csv', str_repeat("Pérou;Lima\n", 2001));

        $this->previewImport($author, $this->draftOf($author), ['file' => $file])
            ->assertUnprocessable()
            ->assertJson([
                'code' => 'import_too_large',
                'message' => 'Ce fichier est trop gros : il doit faire au plus 5 Mo et 2000 lignes.',
            ]);
    }

    public function test_each_line_to_fix_is_shown_with_its_number_and_blocks_the_import(): void
    {
        $author = User::factory()->create();
        $rows = array_map(fn (int $line): array => ["Recto {$line}", "Verso {$line}"], range(1, 100));
        $rows[11] = ['Capitale du Pérou ?', '   '];
        $rows[39] = [str_repeat('a', 5001), 'Verso 40'];
        $rows[59] = ['Recto 60', str_repeat('[a](https://example.org/une-page-assez-longue) ', 400)];
        $rows[79] = ['', 'Verso 80'];

        $response = $this->previewImport($author, $this->draftOf($author), ['file' => $this->xlsxFile($rows)]);

        $response->assertOk()
            ->assertJsonPath('data.question_count', 100)
            ->assertJsonPath('data.error_line_count', 4)
            ->assertJsonPath('data.can_confirm', false)
            ->assertJsonPath('data.rows.11.line', 12)
            ->assertJsonPath('data.rows.11.errors', [['field' => 'verso', 'code' => 'verso_empty', 'message' => 'Le verso est vide.']])
            ->assertJsonPath('data.rows.39.errors', [['field' => 'recto', 'code' => 'recto_too_long', 'message' => 'Le recto dépasse 5 000 caractères.']])
            ->assertJsonPath('data.rows.59.errors.0.code', 'verso_too_long')
            ->assertJsonPath('data.rows.79.errors.0.code', 'recto_empty')
            ->assertJsonPath('data.rows.0.errors', []);
    }

    public function test_a_subject_cannot_go_over_500_questions(): void
    {
        $author = User::factory()->create();
        $subject = $this->draftOf($author);
        Question::factory()->count(450)->for($subject)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create();
        $rows = array_map(fn (int $line): array => ["Recto {$line}", "Verso {$line}"], range(1, 80));

        $this->previewImport($author, $subject, ['file' => $this->xlsxFile($rows)])
            ->assertOk()
            ->assertJsonPath('data.can_confirm', false)
            ->assertJsonPath('data.error_line_count', 0)
            ->assertJsonPath('data.errors', [[
                'code' => 'question_limit_exceeded',
                'remaining' => 50,
                'message' => 'Ce sujet ne peut plus recevoir que 50 questions, sur les 500 autorisées.',
            ]]);
    }

    public function test_a_source_without_any_question_cannot_be_imported(): void
    {
        $author = User::factory()->create();

        $this->previewImport($author, $this->draftOf($author), ['file' => $this->xlsxFile([['Recto', 'Verso'], [null, null]])])
            ->assertOk()
            ->assertJsonPath('data.question_count', 0)
            ->assertJsonPath('data.can_confirm', false)
            ->assertJsonPath('data.errors.0.code', 'import_empty');
    }

    public function test_columns_after_the_second_do_not_block_the_import(): void
    {
        $author = User::factory()->create();
        $file = $this->xlsxFile([['Pérou', 'Lima', 'géographie'], ['Chili', 'Santiago', 'géographie']]);

        $this->previewImport($author, $this->draftOf($author), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.can_confirm', true)
            ->assertJsonPath('data.notices.0.code', 'extra_columns_ignored');
    }

    public function test_a_recto_already_in_the_subject_or_in_the_source_is_pointed_out_without_blocking(): void
    {
        $author = User::factory()->create();
        $subject = $this->draftOf($author);
        Question::factory()->count(3)->for($subject)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create();
        Question::factory()->for($subject)->create(['position' => 4, 'recto_html' => '<p>Capitale du <strong>Pérou</strong> ?</p>']);
        $file = $this->xlsxFile([
            ['capitale du  perou ?', 'Lima'],
            ['Capitale du Chili ?', 'Santiago'],
            ['CAPITALE DU *CHILI* ?', 'Santiago du Chili'],
        ]);

        $this->previewImport($author, $subject, ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.can_confirm', true)
            ->assertJsonPath('data.rows.0.warnings', [[
                'code' => 'duplicate_in_subject',
                'position' => 4,
                'message' => 'Ce recto existe déjà dans le sujet (question 4).',
            ]])
            ->assertJsonPath('data.rows.1.warnings', [])
            ->assertJsonPath('data.rows.2.warnings', [[
                'code' => 'duplicate_in_source',
                'line' => 2,
                'message' => 'Ce recto est le même qu’à la ligne 2.',
            ]]);
    }

    public function test_two_columns_pasted_from_a_spreadsheet_are_read_line_by_line(): void
    {
        $author = User::factory()->create();
        $pasted = "Pérou\tLima\n\"Chili\nou Chile\"\t\"Santiago\"\nBolivie\tSucre";

        $this->previewImport($author, $this->draftOf($author), ['text' => $pasted])
            ->assertOk()
            ->assertJsonPath('data.question_count', 3)
            ->assertJsonPath('data.can_confirm', true)
            ->assertJsonPath('data.rows.0.recto_html', '<p>Pérou</p>')
            ->assertJsonPath('data.rows.1.line', 2)
            ->assertJsonPath('data.rows.1.verso_html', '<p>Santiago</p>')
            ->assertJsonPath('data.rows.2.line', 4);
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function flashcardExports(): array
    {
        return [
            'anki' => ['anki.txt', ['extra_columns_ignored']],
            'quizlet' => ['quizlet.txt', []],
        ];
    }

    /**
     * @param  list<string>  $notices
     */
    #[DataProvider('flashcardExports')]
    public function test_an_anki_or_quizlet_export_is_pasted_as_it_is(string $fixture, array $notices): void
    {
        $author = User::factory()->create();
        $pasted = (string) file_get_contents(__DIR__."/../fixtures/import/{$fixture}");

        $response = $this->previewImport($author, $this->draftOf($author), ['text' => $pasted]);

        $response->assertOk()
            ->assertJsonPath('data.question_count', 2)
            ->assertJsonPath('data.rows.0.recto_html', '<p>Pérou</p>')
            ->assertJsonPath('data.rows.1.verso_html', '<p>Santiago</p>');
        $this->assertSame($notices, array_column($response->json('data.notices'), 'code'));
    }

    public function test_a_pasted_text_without_any_tab_is_refused(): void
    {
        $author = User::factory()->create();

        $this->previewImport($author, $this->draftOf($author), ['text' => "Pérou Lima\nChili Santiago"])
            ->assertUnprocessable()
            ->assertJson([
                'code' => 'import_single_column',
                'message' => 'Le recto et le verso doivent être dans deux colonnes. Copiez deux colonnes de votre tableur, ou séparez-les par une tabulation.',
            ]);
    }

    public function test_the_preview_of_500_formatted_questions_answers_within_3_seconds(): void
    {
        $author = User::factory()->create();
        $subject = $this->draftOf($author);
        $rows = array_map(fn (int $line): array => [
            "Le passé de **verbe {$line}** ?",
            "**forme {$line}**, comme dans :\n- une première phrase\n- une *seconde* phrase avec `du code`",
        ], range(1, 500));
        $file = $this->xlsxFile([['Recto', 'Verso'], ...$rows]);

        $startedAt = microtime(true);
        $response = $this->previewImport($author, $subject, ['file' => $file]);
        $elapsed = microtime(true) - $startedAt;

        $response->assertOk()->assertJsonPath('data.question_count', 500)->assertJsonPath('data.can_confirm', true);
        $this->assertLessThan(3.0, $elapsed, "The preview took {$elapsed} s.");
    }

    public function test_a_source_is_a_file_or_a_text_never_both_nor_none(): void
    {
        $author = User::factory()->create();
        $subject = $this->draftOf($author);

        $this->previewImport($author, $subject, [])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->previewImport($author, $subject, ['file' => $this->referenceXlsx(), 'text' => "Pérou\tLima"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }
}
