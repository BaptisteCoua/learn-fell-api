<?php

namespace Functional\Catalog\Http\Requests;

/**
 * Confirming an import: the same source again, and the id the web app drew for this preview,
 * so that a confirmation sent twice imports once (FR-017, research R6).
 */
class StoreQuestionImportRequest extends QuestionImportRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'import_id' => ['required', 'uuid']];
    }
}
