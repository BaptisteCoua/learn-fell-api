<?php

namespace Functional\Catalog\Enums;

enum SubjectStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';

    /**
     * Published until its author asked to delete their account with everything in it: hidden
     * from everyone but moderation, and published again if the author changes their mind.
     */
    case Withheld = 'withheld';

    /**
     * Only published subjects are listed, searched and readable by the public.
     */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
