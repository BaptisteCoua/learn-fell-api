<?php

namespace Functional\Reminders\Access\Controls;

use Functional\Reminders\Models\ReminderSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * An account reads and changes its own settings only; the row lives and dies with the account.
 */
class ReminderSettingControl extends Control
{
    protected string $model = ReminderSetting::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => in_array($method, ['view', 'update'], true)
                    && $user->hasVerifiedEmail())
                ->should(fn (Model $user, Model $setting): bool => $setting->user_id === $user->getKey())
                ->query(fn (Builder $query, Model $user): Builder => $query->where('reminder_settings.user_id', $user->getKey())),
        ];
    }
}
