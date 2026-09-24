<?php

namespace Functional\Catalog\Enums;

enum SubjectStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';

    /**
     * Only published subjects are listed, searched and readable by the public.
     */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
