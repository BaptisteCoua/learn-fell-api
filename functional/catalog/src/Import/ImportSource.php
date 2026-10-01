<?php

namespace Functional\Catalog\Import;

/**
 * What was read from a pasted text or a file, before any question is built from it.
 */
readonly class ImportSource
{
    /**
     * @param  list<array{line: int, recto: string, verso: string}>  $records
     * @param  list<string>  $notices  codes of `import.notice`
     */
    public function __construct(
        public array $records,
        public array $notices,
    ) {}
}
