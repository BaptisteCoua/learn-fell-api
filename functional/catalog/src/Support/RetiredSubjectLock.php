<?php

namespace Functional\Catalog\Support;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * The lock a moderation retirement puts on a subject (FR-033).
 */
class RetiredSubjectLock
{
    /**
     * A retired subject is read-only for its author (FR-033); a moderator may still edit it.
     */
    public static function ensureEditable(Subject $subject): void
    {
        if ($subject->status === SubjectStatus::Retired && ! Auth::user()?->can('subjects.moderate')) {
            throw new BusinessRuleException('subject_retired');
        }
    }
}
