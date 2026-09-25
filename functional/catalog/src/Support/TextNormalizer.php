<?php

namespace Functional\Catalog\Support;

use Illuminate\Support\Str;

/**
 * One normal form for everything compared or searched without case nor accents:
 * category names, tags and the subject search document.
 */
class TextNormalizer
{
    public static function normalize(string $text): string
    {
        return Str::of($text)->ascii()->lower()->squish()->value();
    }
}
