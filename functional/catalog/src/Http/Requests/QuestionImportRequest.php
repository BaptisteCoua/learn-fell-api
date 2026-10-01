<?php

namespace Functional\Catalog\Http\Requests;

use Functional\Catalog\Import\ImportSource;
use Functional\Catalog\Import\SourceReader;
use Functional\Catalog\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The source of an import: a file, or a pasted text, never both (specs/008-question-import,
 * contract). Its size and its content are checked by SourceReader, which answers with the
 * codes of the contract rather than field errors.
 */
class QuestionImportRequest extends FormRequest
{
    /**
     * Checked before the body: a subject the user may not read answers as one that does not
     * exist, whatever is sent, so a draft never reveals it exists (principle VI).
     */
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject instanceof Subject && Gate::allows('view', $subject);
    }

    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['bail', 'required_without:text', 'prohibits:text', 'file'],
            'text' => ['bail', 'required_without:file', 'string'],
        ];
    }

    public function source(SourceReader $reader): ImportSource
    {
        return $this->hasFile('file')
            ? $reader->fromFile($this->file('file'))
            : $reader->fromText((string) $this->input('text'));
    }
}
