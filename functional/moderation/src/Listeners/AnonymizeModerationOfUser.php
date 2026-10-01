<?php

namespace Functional\Moderation\Listeners;

use Functional\Moderation\Models\ModerationDecision;
use Functional\Moderation\Models\Report;
use Functional\Users\Models\User;

/**
 * Feature 004, FR-020 — the reports and decisions of an erased account stay in the queue and
 * the history, without it: the web shows « Compte supprimé ».
 */
class AnonymizeModerationOfUser
{
    public function handle(User $user): void
    {
        Report::query()->where('reporter_id', $user->getKey())->update(['reporter_id' => null]);
        ModerationDecision::query()->where('admin_id', $user->getKey())->update(['admin_id' => null]);
    }
}
