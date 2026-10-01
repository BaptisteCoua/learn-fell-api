<?php

namespace Functional\Catalog\Tests\Unit;

use Functional\Catalog\Import\ImportSource;
use Functional\Catalog\Import\SourceReader;
use Functional\Catalog\Tests\Concerns\MakesImportSources;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Technical\Osdd\Exceptions\BusinessRuleException;
use Tests\TestCase;

/**
 * specs/008-question-import — FR-003, FR-005, FR-007, FR-008: from delimited text to records.
 */
class SourceReaderTest extends TestCase
{
    use MakesImportSources;

    private function read(string $text, string $separator = "\t"): ImportSource
    {
        return (new SourceReader)->records($text, $separator);
    }

    /**
     * @return list<array{int, string, string}>
     */
    private function rows(ImportSource $source): array
    {
        return array_map(fn (array $record): array => [$record['line'], $record['recto'], $record['verso']], $source->records);
    }

    public function test_each_line_is_a_recto_and_a_verso(): void
    {
        $source = $this->read("Pérou\tLima\nChili\tSantiago\n");

        $this->assertSame([[1, 'Pérou', 'Lima'], [2, 'Chili', 'Santiago']], $this->rows($source));
        $this->assertSame([], $source->notices);
    }

    public function test_a_quoted_cell_keeps_its_separator_its_quotes_and_its_line_breaks(): void
    {
        $source = $this->read("\"Pérou, capitale ?\",\"Lima\nou \"\"la ville des rois\"\"\"\nChili,Santiago\n", ',');

        $this->assertSame([
            [1, 'Pérou, capitale ?', "Lima\nou \"la ville des rois\""],
            [3, 'Chili', 'Santiago'],
        ], $this->rows($source));
    }

    public function test_empty_lines_are_skipped_and_spaces_around_cells_are_removed(): void
    {
        $source = $this->read("  Pérou \t Lima  \n\n \t \nChili\tSantiago\n");

        $this->assertSame([[1, 'Pérou', 'Lima'], [4, 'Chili', 'Santiago']], $this->rows($source));
    }

    public function test_a_cell_of_spaces_is_empty(): void
    {
        $this->assertSame([[1, 'Pérou', '']], $this->rows($this->read("Pérou\t   \n")));
    }

    public function test_the_header_lines_of_an_anki_export_are_skipped(): void
    {
        $source = $this->read("#separator:tab\n#html:false\n#notetype column:3\nPérou\tLima\n");

        $this->assertSame([[4, 'Pérou', 'Lima']], $this->rows($source));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function headers(): array
    {
        return [
            'recto verso' => ["Recto\tVerso"],
            'capitals and accents' => ["QUESTION\tRéponse"],
            'without accent' => ["question\treponse"],
        ];
    }

    #[DataProvider('headers')]
    public function test_a_header_line_is_not_a_question(string $header): void
    {
        $source = $this->read("{$header}\nPérou\tLima\n");

        $this->assertSame([[2, 'Pérou', 'Lima']], $this->rows($source));
        $this->assertSame(['header_ignored'], $source->notices);
    }

    public function test_a_first_line_that_only_looks_like_a_header_is_a_question(): void
    {
        $this->assertSame([[1, 'Recto', 'Lima']], $this->rows($this->read("Recto\tLima\n")));
    }

    public function test_columns_after_the_second_are_ignored_and_said_once(): void
    {
        $source = $this->read("Pérou\tLima\tgéographie\nChili\tSantiago\tgéographie\nBolivie\tSucre\t\n");

        $this->assertSame([[1, 'Pérou', 'Lima'], [2, 'Chili', 'Santiago'], [3, 'Bolivie', 'Sucre']], $this->rows($source));
        $this->assertSame(['extra_columns_ignored'], $source->notices);
    }

    public function test_an_empty_trailing_column_is_not_worth_a_notice(): void
    {
        $this->assertSame([], $this->read("Pérou;Lima;\n", ';')->notices);
    }

    public function test_more_lines_than_allowed_are_refused(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('2000 lignes');

        $this->read(str_repeat("Pérou\tLima\n", 2001));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function csvExports(): array
    {
        return [
            'french excel' => ['excel-fr.csv'],
            'google sheets' => ['google-sheets.csv'],
            'libreoffice' => ['libreoffice.csv'],
        ];
    }

    #[DataProvider('csvExports')]
    public function test_every_csv_export_gives_the_same_questions(string $fixture): void
    {
        $source = (new SourceReader)->fromFile($this->importFixture($fixture));

        $this->assertSame($this->referenceQuestions(), $this->questionsOf($source));
        $this->assertSame(['header_ignored'], $source->notices);
    }

    public function test_the_lines_of_a_csv_are_the_lines_of_the_file(): void
    {
        $source = (new SourceReader)->fromFile($this->importFixture('excel-fr.csv'));

        $this->assertSame([2, 4, 5], array_column(array_slice($source->records, 0, 3), 'line'));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function workbooks(): array
    {
        return [
            'shared strings, as excel and google sheets write them' => [true],
            'inline strings' => [false],
        ];
    }

    #[DataProvider('workbooks')]
    public function test_a_workbook_gives_the_same_questions(bool $sharedStrings): void
    {
        $source = (new SourceReader)->fromFile($this->referenceXlsx($sharedStrings));

        $this->assertSame($this->referenceQuestions(), $this->questionsOf($source));
        $this->assertSame(['header_ignored'], $source->notices);
        $this->assertSame([2, 3, 51], [$source->records[0]['line'], $source->records[1]['line'], $source->records[49]['line']]);
    }

    public function test_only_the_first_sheet_is_read_with_the_values_it_shows(): void
    {
        $source = (new SourceReader)->fromFile($this->twoSheetsXlsx());

        $this->assertSame([
            [1, 'Six fois sept ?', '42'],
            [2, 'Année de la prise de la Bastille ?', '1789'],
        ], $this->rows($source));
        $this->assertSame(['first_sheet_only'], $source->notices);
    }

    public function test_a_line_left_empty_in_a_workbook_keeps_the_numbering(): void
    {
        $source = (new SourceReader)->fromFile($this->xlsxFile([['Pérou', 'Lima'], [null, null], ['Chili', 'Santiago']]));

        $this->assertSame([[1, 'Pérou', 'Lima'], [3, 'Chili', 'Santiago']], $this->rows($source));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableFiles(): array
    {
        return [
            'pdf' => ['not-a-sheet.pdf'],
            'corrupt workbook' => ['corrupt'],
            'pdf renamed as csv' => ['renamed-pdf'],
        ];
    }

    #[DataProvider('unreadableFiles')]
    public function test_a_file_that_is_not_a_readable_spreadsheet_is_refused(string $fixture): void
    {
        $file = match ($fixture) {
            'corrupt' => $this->corruptXlsx(),
            'renamed-pdf' => new UploadedFile(self::IMPORT_FIXTURES.'/not-a-sheet.pdf', 'questions.csv', test: true),
            default => $this->importFixture($fixture),
        };

        try {
            (new SourceReader)->fromFile($file);
            $this->fail('An unreadable file was read.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('import_unreadable', $exception->errorCode);
        }
    }

    public function test_a_file_over_5_megabytes_is_refused(): void
    {
        $file = UploadedFile::fake()->create('questions.csv', 5121, 'text/csv');

        try {
            (new SourceReader)->fromFile($file);
            $this->fail('A file over the limit was read.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('import_too_large', $exception->errorCode);
        }
    }

    /**
     * @return list<array{string, string}>
     */
    private function questionsOf(ImportSource $source): array
    {
        return array_map(fn (array $record): array => [$record['recto'], $record['verso']], $source->records);
    }

    public function test_a_source_without_any_second_column_is_refused(): void
    {
        try {
            $this->read("Pérou\nChili\n");
            $this->fail('A single column was read.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('import_single_column', $exception->errorCode);
        }
    }
}
