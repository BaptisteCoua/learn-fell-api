<?php

namespace Functional\Learning\Listeners;

use Functional\Learning\Models\Learning;
use Functional\Users\Models\User;

/**
 * Feature 004, FR-017 — an erased account takes its learnings along; each one removes its
 * progress and answers (DeleteCardsOfLearning).
 */
class DeleteLearningsOfUser
{
    public function handle(User $user): void
    {
        Learning::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->each(fn (Learning $learning): ?bool => $learning->delete());
    }
}
