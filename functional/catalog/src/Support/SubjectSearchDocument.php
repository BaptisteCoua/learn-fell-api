<?php

namespace Functional\Catalog\Support;

use Functional\Catalog\Models\Subject;

/**
 * The normalized text a subject is searched on: its title, description and tag names.
 */
class SubjectSearchDocument
{
    public static function for(Subject $subject): string
    {
        $tagNames = $subject->tags()->pluck('name')->implode(' ');

        return TextNormalizer::normalize("{$subject->title} {$subject->description} {$tagNames}");
    }
}
