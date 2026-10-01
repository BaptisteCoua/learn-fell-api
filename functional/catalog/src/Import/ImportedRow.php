<?php

namespace Functional\Catalog\Import;

/**
 * A question read from the source, as the preview shows it: the rich text that will be saved,
 * the errors that block the import and the warnings that do not (specs/008-question-import).
 */
class ImportedRow
{
    /**
     * @var list<array{field: string, code: string}>
     */
    private array $errors = [];

    /**
     * @var list<array{code: string, line?: int, position?: int}>
     */
    private array $warnings = [];

    public function __construct(
        public readonly int $line,
        public readonly string $rectoHtml,
        public readonly string $versoHtml,
    ) {}

    public function addError(string $field, string $code): void
    {
        $this->errors[] = ['field' => $field, 'code' => $code];
    }

    /**
     * @param  array{line?: int, position?: int}  $context
     */
    public function addWarning(string $code, array $context): void
    {
        $this->warnings[] = ['code' => $code, ...$context];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array{line: int, recto_html: string, verso_html: string, errors: list<array<string, mixed>>, warnings: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'recto_html' => $this->rectoHtml,
            'verso_html' => $this->versoHtml,
            'errors' => array_map(fn (array $error): array => [
                ...$error,
                'message' => __("import.row.{$error['code']}"),
            ], $this->errors),
            'warnings' => array_map(fn (array $warning): array => [
                ...$warning,
                'message' => __("import.warning.{$warning['code']}", array_diff_key($warning, ['code' => true])),
            ], $this->warnings),
        ];
    }
}
