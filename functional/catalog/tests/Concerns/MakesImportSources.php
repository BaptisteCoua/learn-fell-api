<?php

namespace Functional\Catalog\Tests\Concerns;

use Functional\Catalog\Models\Subject;
use Functional\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use ZipArchive;

/**
 * Sources of an import as the spreadsheets write them (specs/008-question-import, SC-002).
 *
 * The CSV files of `fixtures/import` reproduce, from `questions.json`, the bytes each tool
 * writes: a French Excel (Windows-1252, `;`, CRLF), Google Sheets (UTF-8, `,`, CRLF) and
 * LibreOffice (UTF-8 with BOM, `,`, LF). The XLSX files are built here with OpenSpout: shared
 * strings, as Excel and Google Sheets write them, or inline strings.
 */
trait MakesImportSources
{
    private const IMPORT_FIXTURES = __DIR__.'/../fixtures/import';

    /**
     * The 50 questions every fixture holds, after a header line.
     *
     * @return list<array{string, string}>
     */
    protected function referenceQuestions(): array
    {
        return json_decode((string) file_get_contents(self::IMPORT_FIXTURES.'/questions.json'), true);
    }

    protected function importFixture(string $name): UploadedFile
    {
        return new UploadedFile(self::IMPORT_FIXTURES."/{$name}", $name, test: true);
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows  written as they are, header included
     */
    protected function xlsxFile(array $rows, bool $sharedStrings = true, string $name = 'questions.xlsx'): UploadedFile
    {
        $path = $this->writeWorkbook([$rows], $sharedStrings);

        return new UploadedFile($path, $name, test: true);
    }

    /**
     * The reference questions under a « Recto | Verso » header.
     */
    protected function referenceXlsx(bool $sharedStrings = true): UploadedFile
    {
        return $this->xlsxFile([['Recto', 'Verso'], ...$this->referenceQuestions()], $sharedStrings);
    }

    /**
     * Two sheets: on the first, a formula whose value Excel kept (`6*7`, shown as 42) and a
     * whole number; the second sheet is never read.
     */
    protected function twoSheetsXlsx(): UploadedFile
    {
        $path = $this->writeWorkbook([
            [
                new Row([StringCell::fromValue('Six fois sept ?'), new FormulaCell('=6*7')]),
                new Row([StringCell::fromValue('Année de la prise de la Bastille ?'), NumericCell::fromValue(1789)]),
            ],
            [['Feuille ignorée', 'jamais lue']],
        ], sharedStrings: true);

        $this->keepComputedValue($path, '<f>6*7</f>', '42');

        return new UploadedFile($path, 'deux-feuilles.xlsx', test: true);
    }

    /**
     * Calls the preview as the web app does: a file in a multipart body, a text in JSON.
     *
     * @param  array<string, mixed>  $payload  `file`, `text` and, to confirm, `import_id`
     */
    protected function previewImport(?User $user, Subject $subject, array $payload): TestResponse
    {
        return $this->sendImport($user, "/api/subjects/{$subject->id}/question-import/preview", $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function confirmImport(?User $user, Subject $subject, array $payload): TestResponse
    {
        return $this->sendImport($user, "/api/subjects/{$subject->id}/question-import", $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendImport(?User $user, string $uri, array $payload): TestResponse
    {
        if ($user === null) {
            $this->app['auth']->forgetGuards();
        } else {
            $this->actingAs($user);
        }

        $hasFile = collect($payload)->contains(fn ($value): bool => $value instanceof UploadedFile);

        return $hasFile
            ? $this->post($uri, $payload, ['Accept' => 'application/json'])
            : $this->postJson($uri, $payload);
    }

    protected function corruptXlsx(): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($path, "PK\x03\x04 ceci n'est plus un classeur");

        return new UploadedFile($path, 'abime.xlsx', test: true);
    }

    /**
     * @param  list<list<Row|list<string|int|float|null>>>  $sheets
     */
    private function writeWorkbook(array $sheets, bool $sharedStrings): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        $writer = new Writer(new Options(SHOULD_USE_INLINE_STRINGS: ! $sharedStrings));
        $writer->openToFile($path);

        foreach ($sheets as $index => $rows) {
            if ($index > 0) {
                $writer->addNewSheetAndMakeItCurrent();
            }

            foreach ($rows as $row) {
                $writer->addRow($row instanceof Row ? $row : Row::fromValues($row));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * OpenSpout writes the formula alone; Excel also writes the value it computed.
     */
    private function keepComputedValue(string $path, string $formula, string $value): void
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/sheet1.xml', str_replace($formula.'</c>', "{$formula}<v>{$value}</v></c>", $sheet));
        $zip->close();
    }
}
