<?php

namespace Functional\Catalog\Tests\Unit;

use Functional\Catalog\Import\SourceDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * specs/008-question-import — FR-004, research R2: the encoding and the separator of a CSV are
 * found without asking the author.
 */
class SourceDecoderTest extends TestCase
{
    public function test_the_utf8_byte_order_mark_is_removed(): void
    {
        $this->assertSame("Recto,Verso\n", (new SourceDecoder)->decode("\xEF\xBB\xBFRecto,Verso\n"));
    }

    public function test_valid_utf8_is_kept_as_it_is(): void
    {
        $this->assertSame("Pérou;Lima € œ\n", (new SourceDecoder)->decode("Pérou;Lima € œ\n"));
    }

    public function test_windows_1252_from_a_french_excel_is_converted(): void
    {
        $excel = mb_convert_encoding("Pérou;Lima € œ\n", 'Windows-1252', 'UTF-8');

        $this->assertSame("Pérou;Lima € œ\n", (new SourceDecoder)->decode($excel));
    }

    public function test_an_unreadable_byte_shows_as_a_replacement_character(): void
    {
        $this->assertSame("Lima \u{FFFD}\n", (new SourceDecoder)->decode("Lima \x81\n"));
    }

    public function test_every_line_ending_becomes_a_line_feed(): void
    {
        $this->assertSame("a;b\nc;d\ne;f", (new SourceDecoder)->decode("a;b\r\nc;d\re;f"));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function separators(): array
    {
        return [
            'semicolon of a french excel' => ["Recto;Verso\nPérou;Lima\nChili;Santiago\n", ';'],
            'comma of google sheets' => ["Recto,Verso\nPérou,Lima\nChili,Santiago\n", ','],
            'tab' => ["Pérou\tLima\nChili\tSantiago\n", "\t"],
            'commas inside sentences' => ["Capitale du Pérou, en Amérique;Lima\nPays de Santiago;Chili\n", ';'],
            'quoted comma' => ["\"Pérou, capitale\",Lima\n\"Chili, capitale\",Santiago\n", ','],
            'tie goes to the tab' => ["a\tb;c\nd\te;f\n", "\t"],
            'one column only' => ["Pérou\nChili\n", null],
        ];
    }

    #[DataProvider('separators')]
    public function test_the_separator_is_the_one_that_gives_two_columns_most_often(string $text, ?string $separator): void
    {
        $this->assertSame($separator, (new SourceDecoder)->detectSeparator($text));
    }
}
