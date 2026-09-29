<?php

namespace Functional\Reminders\Http\Controllers;

use Functional\Reminders\Enums\EmailDisabledReason;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Support\ReminderScheduler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Turns the reminder emails off from a link, without a session (FR-014, FR-015). The signature
 * is checked before anything is read, and every invalid link answers alike, so that a link
 * never tells whether an account exists (FR-016).
 */
class UnsubscribeController
{
    public function __invoke(Request $request, int $id, ReminderScheduler $scheduler): Response
    {
        if (! $request->hasValidRelativeSignature()) {
            throw new BusinessRuleException('invalid_link', 403);
        }

        $setting = ReminderSetting::query()->with('user')->where('user_id', $id)->first();

        if ($setting === null || (string) $setting->unsubscribe_version !== (string) $request->query('v')) {
            throw new BusinessRuleException('invalid_link', 403);
        }

        if ($setting->email_enabled) {
            $setting->forceFill([
                'email_enabled' => false,
                'email_disabled_reason' => EmailDisabledReason::Unsubscribed,
            ]);
            $scheduler->refresh($setting);
        }

        return response()->noContent();
    }
}
