<?php

namespace Functional\Catalog\Import;

use Generator;

/**
 * Reads delimited text the way spreadsheets write it (RFC 4180): doubled quotes, separators
 * and line breaks inside a quoted cell. Each record knows the line of the source it starts on.
 */
class DelimitedRecords
{
    /**
     * @return Generator<int, array{line: int, cells: list<?string>}>
     */
    public static function read(string $text, string $separator): Generator
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);

        $line = 1;
        $offset = 0;

        try {
            while (($cells = fgetcsv($stream, null, $separator, '"', '')) !== false) {
                yield ['line' => $line, 'cells' => $cells];

                $nextOffset = (int) ftell($stream);
                $line += substr_count($text, "\n", $offset, $nextOffset - $offset);
                $offset = $nextOffset;
            }
        } finally {
            fclose($stream);
        }
    }
}
