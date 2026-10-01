<?php

namespace Functional\Catalog\Tests\Unit;

use Functional\Catalog\Import\MarkdownCell;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * specs/008-question-import — FR-024, FR-025, SC-007: only the formatting CINQ allows is read
 * from a cell, and only in its complete form; everything else stays the text the author wrote.
 */
class MarkdownCellTest extends TestCase
{
    private function html(string $cell): string
    {
        return str_replace("\n", '', (new MarkdownCell)->toHtml($cell));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function allowedFormatting(): array
    {
        return [
            'bold' => ['Le passé de **go**', '<p>Le passé de <strong>go</strong></p>'],
            'italic with asterisks' => ['Le passé de *go*', '<p>Le passé de <em>go</em></p>'],
            'italic with underscores' => ['Le passé de _go_', '<p>Le passé de <em>go</em></p>'],
            'bulleted list' => ["- I went\n- you went", '<ul><li>I went</li><li>you went</li></ul>'],
            'numbered list' => ["1. Lima\n2. Cusco", '<ol><li>Lima</li><li>Cusco</li></ol>'],
            'inline code' => ['Tapez `git status`', '<p>Tapez <code>git status</code></p>'],
            'fenced code' => ["```\ngit switch -c demo\n```", '<pre><code>git switch -c demo</code></pre>'],
            'link' => ['[la doc](https://git-scm.com)', '<p><a rel="noopener nofollow ugc" href="https://git-scm.com">la doc</a></p>'],
            'autolink' => ['<https://git-scm.com>', '<p><a rel="noopener nofollow ugc" href="https://git-scm.com">https://git-scm.com</a></p>'],
        ];
    }

    #[DataProvider('allowedFormatting')]
    public function test_the_allowed_formatting_is_converted(string $cell, string $expected): void
    {
        $this->assertSame($expected, $this->html($cell));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function formattingCinqDoesNotAllow(): array
    {
        return [
            'heading' => ['# Titre', '<p># Titre</p>'],
            'quote' => ['> citation', '<p>&gt; citation</p>'],
            'thematic break' => ['---', '<p>---</p>'],
            'image' => ['![un chat](https://example.org/chat.png)', '<p>![un chat](https://example.org/chat.png)</p>'],
            'html tag' => ['<b>gras</b>', '<p>&lt;b&gt;gras&lt;/b&gt;</p>'],
            'script' => ['<script>alert(1)</script>', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
            'indented code' => ['    ls -la', '<p>ls -la</p>'],
        ];
    }

    #[DataProvider('formattingCinqDoesNotAllow')]
    public function test_other_syntaxes_stay_the_text_that_was_written(string $cell, string $expected): void
    {
        $this->assertSame($expected, $this->html($cell));
    }

    public function test_an_unsafe_link_never_becomes_an_active_link(): void
    {
        $html = $this->html('[cliquez](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('href=', $html);
        $this->assertStringContainsString('cliquez', $html);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everydayTexts(): array
    {
        return [
            'multiplication with spaces' => ['2 * 3 * 4 = 24'],
            'multiplication without spaces' => ['5*3*2'],
            'snake case' => ['nom_de_variable'],
            'several underscores' => ['a_b_c'],
            'trailing asterisk' => ['prix : 5 € *'],
            'unclosed underscore' => ['_début sans fin'],
            'lonely double asterisk' => ['**'],
            'footnote mark' => ['Voir la note*'],
            'bold glued inside a word' => ['un**mot**collé'],
        ];
    }

    #[DataProvider('everydayTexts')]
    public function test_ordinary_symbols_are_never_read_as_formatting(string $cell): void
    {
        $this->assertSame('<p>'.$cell.'</p>', $this->html($cell));
    }

    public function test_a_backslash_keeps_a_symbol_as_it_is(): void
    {
        $this->assertSame('<p>*pas en italique*</p>', $this->html('\*pas en italique\*'));
    }

    public function test_a_line_break_in_a_cell_is_kept(): void
    {
        $html = (new MarkdownCell)->toHtml("ligne 1\nligne 2");

        $this->assertMatchesRegularExpression('#^<p>ligne 1<br ?/?>\s*ligne 2</p>$#', $html);
    }
}
