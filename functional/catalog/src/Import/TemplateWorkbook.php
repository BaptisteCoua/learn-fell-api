<?php

namespace Functional\Catalog\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The workbook an author starts from: the header and two examples, one with formatting
 * (specs/008-question-import, FR-021, research R10). Its texts come from the translations.
 */
class TemplateWorkbook
{
    public function fileName(): string
    {
        return __('import.template.file_name');
    }

    /**
     * Writes the workbook to a temporary file and returns its path.
     */
    public function write(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'template');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([__('import.template.recto'), __('import.template.verso')]));

        foreach (trans('import.template.examples') as $example) {
            $writer->addRow(Row::fromValues($example));
        }

        $writer->close();

        return $path;
    }
}
