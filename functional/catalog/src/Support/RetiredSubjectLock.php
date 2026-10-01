<?php

namespace Functional\Catalog\Support;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * The lock a moderation retirement puts on a subject (FR-033), the one its author's deletion
 * request puts on a withheld subject, and the one on a subject left without author (feature 004).
 */
class RetiredSubjectLock
{
    /**
     * A retired or withheld subject is read-only for its author (FR-033); a moderator may
     * still edit it. A subject its erased author left to the community is read-only for
     * everyone: moderation may only retire it, through its decisions.
     */
    public static function ensureEditable(Subject $subject): void
    {
        if ($subject->author_id === null) {
            throw new BusinessRuleException('subject_authorless');
        }

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
