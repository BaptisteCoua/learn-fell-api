<?php

namespace Functional\Catalog\Actions;

use Functional\Catalog\Import\ImportedRow;
use Functional\Catalog\Import\ImportSource;
use Functional\Catalog\Import\QuestionImportPreview;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Support\RetiredSubjectLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Adds every question of a source at the end of a subject, or none (specs/008-question-import,
 * FR-014 to FR-017, research R6). The subject is locked while its questions are counted and
 * placed; each question is created as a model, so that it reaches the learners of the subject
 * exactly as a question written by hand does (FR-020, research R7).
 */
class ImportQuestions
{
    /**
     * @return array{imported: int, already_imported: bool}
     */
    public function handle(Subject $subject, ImportSource $source, string $importId): array
    {
        return DB::transaction(function () use ($subject, $source, $importId): array {
            $subject = Subject::query()->lockForUpdate()->findOrFail($subject->getKey());
            Gate::authorize('update', $subject);

            $alreadyImported = $subject->questions()->where('import_id', $importId)->count();

            if ($alreadyImported > 0) {
                return ['imported' => $alreadyImported, 'already_imported' => true];
            }

            RetiredSubjectLock::ensureEditable($subject);

            $preview = new QuestionImportPreview($subject, $source);

            if (! $preview->canConfirm()) {
                throw new BusinessRuleException('import_has_errors');
            }

            $position = (int) $subject->questions()->max('position');

            foreach ($preview->rows as $row) {
                $this->create($subject, $row, ++$position, $importId);
            }

            return ['imported' => count($preview->rows), 'already_imported' => false];
        });
    }

    private function create(Subject $subject, ImportedRow $row, int $position, string $importId): void
    {
        Question::query()->create([
            'subject_id' => $subject->getKey(),
            'recto_html' => $row->rectoHtml,
            'verso_html' => $row->versoHtml,
            'position' => $position,
            'import_id' => $importId,
        ]);
    }
}
