<?php

namespace Functional\Catalog\Import;

use Functional\Catalog\Models\Subject;
use Functional\Catalog\Rest\Resources\QuestionResource;
use Functional\Catalog\Rules\VisibleTextLength;
use Functional\Catalog\Support\TextNormalizer;

/**
 * The questions a source would add to a subject, as the author sees them before confirming
 * (specs/008-question-import, FR-010 to FR-012, FR-026, FR-027). Nothing is saved.
 */
class QuestionImportPreview
{
    /**
     * @var list<ImportedRow>
     */
    public readonly array $rows;

    /**
     * @var list<array{code: string, remaining?: int}>
     */
    private array $errors = [];

    public function __construct(
        private readonly Subject $subject,
        private readonly ImportSource $source,
        MarkdownCell $markdown = new MarkdownCell,
    ) {
        $this->rows = array_map(fn (array $record): ImportedRow => new ImportedRow(
            $record['line'],
            $markdown->toHtml($record['recto']),
            $markdown->toHtml($record['verso']),
        ), $source->records);

        $this->checkRows();
        $this->checkSource();
        $this->pointOutDuplicates();
    }

    public function canConfirm(): bool
    {
        return $this->rows !== [] && $this->errors === [] && $this->errorLineCount() === 0;
    }

    public function errorLineCount(): int
    {
        return count(array_filter($this->rows, fn (ImportedRow $row): bool => $row->hasErrors()));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rows' => array_map(fn (ImportedRow $row): array => $row->toArray(), $this->rows),
            'notices' => array_map(fn (string $code): array => [
                'code' => $code,
                'message' => __("import.notice.{$code}"),
            ], $this->source->notices),
            'errors' => array_map(fn (array $error): array => [
                ...$error,
                'message' => __("errors.{$error['code']}", [...$error, 'max' => Subject::MAX_QUESTIONS]),
            ], $this->errors),
            'question_count' => count($this->rows),
            'error_line_count' => $this->errorLineCount(),
            'can_confirm' => $this->canConfirm(),
        ];
    }

    /**
     * A recto and a verso of 1 to 5,000 visible characters, as in the editor (FR-011).
     */
    private function checkRows(): void
    {
        foreach ($this->rows as $row) {
            foreach (['recto' => $row->rectoHtml, 'verso' => $row->versoHtml] as $field => $html) {
                $length = VisibleTextLength::of($html);

                if ($length === 0) {
                    $row->addError($field, "{$field}_empty");
                } elseif ($length > VisibleTextLength::MAX || mb_strlen($html) > QuestionResource::MAX_HTML_LENGTH) {
                    $row->addError($field, "{$field}_too_long");
                }
            }
        }
    }

    /**
     * At least one question, and no more than the subject can still receive (FR-011, FR-012).
     */
    private function checkSource(): void
    {
        if ($this->rows === []) {
            $this->errors[] = ['code' => 'import_empty'];

            return;
        }

        $remaining = max(0, Subject::MAX_QUESTIONS - $this->subject->questions()->count());

        if (count($this->rows) > $remaining) {
            $this->errors[] = ['code' => 'question_limit_exceeded', 'remaining' => $remaining];
        }
    }

    /**
     * A recto the subject or an earlier line already has, whatever its case, accents, spaces
     * and formatting, is pointed out without blocking the import (FR-026).
     */
    private function pointOutDuplicates(): void
    {
        $positionsByRecto = [];

        foreach ($this->subject->questions()->get(['recto_html', 'position']) as $question) {
            $positionsByRecto[self::comparable($question->recto_html)] ??= $question->position;
        }

        $linesByRecto = [];

        foreach ($this->rows as $row) {
            $recto = self::comparable($row->rectoHtml);

            if ($recto === '') {
                continue;
            }

            if (isset($positionsByRecto[$recto])) {
                $row->addWarning('duplicate_in_subject', ['position' => $positionsByRecto[$recto]]);
            } elseif (isset($linesByRecto[$recto])) {
                $row->addWarning('duplicate_in_source', ['line' => $linesByRecto[$recto]]);
            }

            $linesByRecto[$recto] ??= $row->line;
        }
    }

    private static function comparable(string $html): string
    {
        return TextNormalizer::normalize(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }
}
