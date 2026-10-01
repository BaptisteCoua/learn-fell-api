<?php

namespace Functional\Catalog\Http\Controllers;

use Functional\Catalog\Actions\ImportQuestions;
use Functional\Catalog\Http\Requests\StoreQuestionImportRequest;
use Functional\Catalog\Import\SourceReader;
use Functional\Catalog\Models\Subject;
use Illuminate\Http\JsonResponse;

/**
 * Confirms an import: the source is read again and every check runs again before anything is
 * added (specs/008-question-import, FR-014). A confirmation already applied answers 200.
 */
class StoreQuestionImportController
{
    public function __invoke(StoreQuestionImportRequest $request, Subject $subject, SourceReader $reader, ImportQuestions $importQuestions): JsonResponse
    {
        $result = $importQuestions->handle($subject, $request->source($reader), (string) $request->input('import_id'));

        return new JsonResponse(
            ['data' => ['imported' => $result['imported']]],
            $result['already_imported'] ? 200 : 201,
        );
    }
}
