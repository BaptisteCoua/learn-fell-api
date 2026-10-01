<?php

namespace Functional\Catalog\Http\Controllers;

use Functional\Catalog\Http\Requests\QuestionImportRequest;
use Functional\Catalog\Import\QuestionImportPreview;
use Functional\Catalog\Import\SourceReader;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Support\RetiredSubjectLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Shows what an import would add to a subject, saving nothing (specs/008-question-import,
 * FR-010 to FR-013). Only who may add a question by hand may preview (FR-001).
 */
class PreviewQuestionImportController
{
    public function __invoke(QuestionImportRequest $request, Subject $subject, SourceReader $reader): JsonResponse
    {
        Gate::authorize('update', $subject);
        RetiredSubjectLock::ensureEditable($subject);

        $preview = new QuestionImportPreview($subject, $request->source($reader));

        return new JsonResponse(['data' => $preview->toArray()], options: JSON_UNESCAPED_UNICODE);
    }
}
