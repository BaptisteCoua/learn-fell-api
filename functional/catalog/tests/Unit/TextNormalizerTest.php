<?php

namespace Functional\Catalog\Tests\Unit;

use Functional\Catalog\Support\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TextNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function texts(): array
    {
        return [
            'accents and capitals' => ['Verbes Irréguliers', 'verbes irreguliers'],
            'extra spaces' => ['  Grammaire   anglaise ', 'grammaire anglaise'],
            'ligatures and cedillas' => ['Cœur et Façade', 'coeur et facade'],
            'already normal' => ['histoire', 'histoire'],
        ];
    }

    #[DataProvider('texts')]
    public function test_it_normalizes_case_accents_and_spaces(string $text, string $expected): void
    {
        $this->assertSame($expected, TextNormalizer::normalize($text));
    }
}
