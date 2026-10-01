<?php

namespace Functional\Catalog\Import;

use DateInterval;
use DateTimeInterface;
use Functional\Catalog\Support\TextNormalizer;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\TextRunCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Technical\Osdd\Exceptions\BusinessRuleException;
use Throwable;

/**
 * Reads the questions of a pasted text or a file: one record per question, its recto in the
 * first column and its verso in the second (specs/008-question-import, FR-003 to FR-009).
 */
class SourceReader
{
    /**
     * Header lines of an Anki text export: `#separator:tab`, `#html:true`, `#notetype column:3`.
     */
    private const ANKI_HEADER = '/^#[a-z ]+:/i';

    /**
     * What `finfo` reports for a text file, empty files included.
     */
    private const TEXT_MIME_TYPES = ['application/csv', 'application/x-empty', 'inode/x-empty'];

    public function __construct(private readonly SourceDecoder $decoder = new SourceDecoder) {}

    /**
     * A pasted text: columns copied from a spreadsheet, or an Anki or Quizlet export, are
     * separated by tabs (FR-005).
     */
    public function fromText(string $text): ImportSource
    {
        $this->ensureWithinSizeLimit(strlen($text));

        return $this->records($this->decoder->decode($text), "\t");
    }

    /**
     * A CSV, TSV or TXT file is read as delimited text, an XLSX workbook by its first sheet.
     * The content decides, not the name: a PDF renamed `.csv` is refused (FR-002, FR-009).
     */
    public function fromFile(UploadedFile $file): ImportSource
    {
        $this->ensureWithinSizeLimit((int) $file->getSize());

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, config('catalog.import.extensions'), true)) {
            throw new BusinessRuleException('import_unreadable');
        }

        return $extension === 'xlsx' ? $this->fromWorkbook($file->getRealPath()) : $this->fromDelimitedFile($file);
    }

    public function records(string $text, string $separator): ImportSource
    {
        $this->ensureWithinLineLimit(substr_count(rtrim($text, "\n"), "\n") + 1);

        return $this->fromRows(DelimitedRecords::read($text, $separator));
    }

    private function fromDelimitedFile(UploadedFile $file): ImportSource
    {
        $mimeType = (string) $file->getMimeType();

        if (! str_starts_with($mimeType, 'text/') && ! in_array($mimeType, self::TEXT_MIME_TYPES, true)) {
            throw new BusinessRuleException('import_unreadable');
        }

        $text = $this->decoder->decode((string) file_get_contents($file->getRealPath()));
        $separator = $this->decoder->detectSeparator($text);

        if ($separator === null) {
            if (trim($text) === '') {
                return new ImportSource([], []);
            }

            throw new BusinessRuleException('import_single_column');
        }

        return $this->records($text, $separator);
    }

    /**
     * Rows are numbered as the spreadsheet numbers them, empty rows included (FR-010).
     */
    private function fromWorkbook(string $path): ImportSource
    {
        $reader = new Reader(new Options(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $rows = [];
        $sheetCount = 0;

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                if (++$sheetCount > 1) {
                    break;
                }

                foreach ($sheet->getRowIterator() as $index => $row) {
                    $this->ensureWithinLineLimit($index);
                    $rows[] = ['line' => $index, 'cells' => $this->cellsOf($row)];
                }
            }
        } catch (BusinessRuleException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new BusinessRuleException('import_unreadable');
        } finally {
            $reader->close();
        }

        return $this->fromRows($rows, $sheetCount > 1 ? ['first_sheet_only'] : []);
    }

    /**
     * A row may skip the cells left empty: their place is kept, so the verso stays second.
     *
     * @return list<string>
     */
    private function cellsOf(Row $row): array
    {
        $cells = [];

        for ($index = 0; $index < $row->getNumCells(); $index++) {
            $cells[] = isset($row->cells[$index]) ? $this->shownValue($row->cells[$index]) : '';
        }

        return $cells;
    }

    /**
     * The value a spreadsheet shows: the value Excel computed for a formula, a whole number
     * without decimals, a date as the French day/month/year, the text of a cell with rich text.
     */
    private function shownValue(Cell $cell): string
    {
        if ($cell instanceof TextRunCell) {
            return $cell->getStringValue();
        }

        $value = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();

        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_float($value) && floor($value) === $value => (string) (int) $value,
            $value instanceof DateTimeInterface => $value->format('d/m/Y'),
            $value instanceof DateInterval => $value->format('%H:%I:%S'),
            default => (string) $value,
        };
    }

    /**
     * @param  iterable<array{line: int, cells: list<?string>}>  $rows
     * @param  list<string>  $notices
     */
    private function fromRows(iterable $rows, array $notices = []): ImportSource
    {
        $records = [];
        $hasTwoColumns = false;
        $readsData = false;

        foreach ($rows as ['line' => $line, 'cells' => $rawCells]) {
            $cells = array_map(self::trimCell(...), $rawCells === [] ? [''] : $rawCells);

            if (! $readsData && preg_match(self::ANKI_HEADER, $cells[0]) === 1) {
                continue;
            }

            if (implode('', $cells) === '') {
                continue;
            }

            if (! $readsData) {
                $readsData = true;

                if ($this->isHeader($cells)) {
                    $notices[] = 'header_ignored';

                    continue;
                }
            }

            $hasTwoColumns = $hasTwoColumns || count($cells) >= 2;

            if (implode('', array_slice($cells, 2)) !== '' && ! in_array('extra_columns_ignored', $notices, true)) {
                $notices[] = 'extra_columns_ignored';
            }

            $records[] = ['line' => $line, 'recto' => $cells[0], 'verso' => $cells[1] ?? ''];
        }

        if ($records !== [] && ! $hasTwoColumns) {
            throw new BusinessRuleException('import_single_column');
        }

        return new ImportSource($records, $notices);
    }

    private function ensureWithinSizeLimit(int $bytes): void
    {
        if ($bytes > config('catalog.import.max_kilobytes') * 1024) {
            $this->refuseAsTooLarge();
        }
    }

    private function ensureWithinLineLimit(int $lines): void
    {
        if ($lines > config('catalog.import.max_lines')) {
            $this->refuseAsTooLarge();
        }
    }

    private function refuseAsTooLarge(): never
    {
        throw new BusinessRuleException('import_too_large', replace: ['lines' => config('catalog.import.max_lines')]);
    }

    /**
     * @param  list<string>  $cells
     */
    private function isHeader(array $cells): bool
    {
        $firstTwo = array_map(TextNormalizer::normalize(...), array_slice($cells, 0, 2));

        foreach (trans('import.headers') as $header) {
            if ($firstTwo === array_map(TextNormalizer::normalize(...), $header)) {
                return true;
            }
        }

        return false;
    }

    private static function trimCell(?string $cell): string
    {
        return (string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', (string) $cell);
    }
}
