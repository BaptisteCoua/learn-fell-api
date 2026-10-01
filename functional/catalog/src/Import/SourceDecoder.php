<?php

namespace Functional\Catalog\Import;

/**
 * Finds the encoding and the separator of a delimited text, which a French Excel and the free
 * spreadsheets write differently (specs/008-question-import, FR-004, research R2).
 */
class SourceDecoder
{
    /**
     * Candidates in order of preference when two of them read as many lines in two columns.
     */
    public const SEPARATORS = ["\t", ';', ','];

    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    private const SAMPLED_RECORDS = 20;

    /**
     * UTF-8 is kept, anything else is read as Windows-1252, the encoding of a French Excel. A
     * byte Windows-1252 does not define shows as U+FFFD, for the author to notice in the
     * preview. Line endings become line feeds.
     */
    public function decode(string $bytes): string
    {
        if (str_starts_with($bytes, self::BYTE_ORDER_MARK)) {
            $bytes = substr($bytes, strlen(self::BYTE_ORDER_MARK));
        }

        if (! mb_check_encoding($bytes, 'UTF-8')) {
            $bytes = $this->fromWindows1252($bytes);
        }

        return str_replace(["\r\n", "\r"], "\n", $bytes);
    }

    /**
     * The separator that reads the most of the first records in two columns or more; null
     * when none of them does.
     */
    public function detectSeparator(string $text): ?string
    {
        $bestSeparator = null;
        $bestScore = 0;

        foreach (self::SEPARATORS as $separator) {
            $score = $this->recordsWithTwoColumns($text, $separator);

            if ($score > $bestScore) {
                $bestSeparator = $separator;
                $bestScore = $score;
            }
        }

        return $bestSeparator;
    }

    private function recordsWithTwoColumns(string $text, string $separator): int
    {
        $count = 0;
        $sampled = 0;

        foreach (DelimitedRecords::read($text, $separator) as $record) {
            if ($record['cells'] === [null]) {
                continue;
            }

            if (count($record['cells']) >= 2) {
                $count++;
            }

            if (++$sampled === self::SAMPLED_RECORDS) {
                break;
            }
        }

        return $count;
    }

    /**
     * mbstring maps the five bytes Windows-1252 leaves undefined to C1 control characters,
     * which no text holds: they become U+FFFD.
     */
    private function fromWindows1252(string $bytes): string
    {
        $text = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');

        return (string) preg_replace('/[\x{0080}-\x{009F}]/u', "\u{FFFD}", $text);
    }
}
