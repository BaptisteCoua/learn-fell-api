<?php

namespace Functional\Reminders\Rest\Actions;

use Functional\Reminders\Models\ReminderSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Lomkit\Rest\Actions\Action;

/**
 * "Plus tard" in the proposal after the first "Apprendre ce sujet" (FR-002): the proposal is
 * not made again, and nothing is turned on.
 */
class DismissProposal extends Action
{
    public function uriKey(): string
    {
        return 'dismiss-proposal';
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, ReminderSetting>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        ReminderSetting::query()
            ->where('user_id', Auth::id())
            ->whereNull('proposal_seen_at')
            ->update(['proposal_seen_at' => now()]);
    }
}
