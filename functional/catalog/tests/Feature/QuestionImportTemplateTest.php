<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\MakesImportSources;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * specs/008-question-import — FR-021: a template to start from, which imports as it is.
 */
class QuestionImportTemplateTest extends TestCase
{
    use MakesImportSources, RefreshDatabase;

    private function downloadTemplate(): string
    {
        $response = $this->get('/api/question-import/template');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('modele-questions.xlsx');

        $path = (string) tempnam(sys_get_temp_dir(), 'template').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        return $path;
    }

    public function test_the_template_holds_a_header_and_two_examples(): void
    {
        $reader = new Reader;
        $reader->open($this->downloadTemplate());
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }

        $reader->close();

        $this->assertSame([
            ['Recto', 'Verso'],
            ['Quelle est la capitale du Pérou ?', 'Lima'],
            ['Le passé de **go** ?', "**went**, comme dans :\n- I went home"],
        ], $rows);
    }

    public function test_the_template_imports_as_it_is(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->create();
        $template = new UploadedFile($this->downloadTemplate(), 'modele-questions.xlsx', test: true);

        $this->previewImport($author, $subject, ['file' => $template])
            ->assertOk()
            ->assertJsonPath('data.question_count', 2)
            ->assertJsonPath('data.can_confirm', true)
            ->assertJsonPath('data.notices.0.code', 'header_ignored')
            ->assertJsonPath('data.rows.1.recto_html', '<p>Le passé de <strong>go</strong> ?</p>');
    }
}
