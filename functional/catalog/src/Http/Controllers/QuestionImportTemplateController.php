<?php

namespace Functional\Catalog\Http\Controllers;

use Functional\Catalog\Import\TemplateWorkbook;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The template workbook of an import (specs/008-question-import, FR-021), opened by a link of
 * the web app.
 */
class QuestionImportTemplateController
{
    public function __invoke(TemplateWorkbook $template): BinaryFileResponse
    {
        return response()
            ->download($template->write(), $template->fileName(), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
