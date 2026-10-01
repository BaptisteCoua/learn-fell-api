<?php

namespace Functional\Catalog\Support;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * The lock a moderation retirement puts on a subject (FR-033), and the one its author's
 * deletion request puts on a withheld subject (feature 004).
 */
class RetiredSubjectLock
{
    /**
     * A retired or withheld subject is read-only for its author (FR-033); a moderator may
     * still edit it.
     */
    public static function ensureEditable(Subject $subject): void
    {
        if (Auth::user()?->can('subjects.moderate')) {
            return;
        }

        if ($subject->status === SubjectStatus::Retired) {
            throw new BusinessRuleException('subject_retired');
        }

        if ($subject->status === SubjectStatus::Withheld) {
            throw new BusinessRuleException('subject_withheld');
        }
    }
}
