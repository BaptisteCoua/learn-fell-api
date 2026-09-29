<?php

namespace Functional\Reminders\Access\Controls;

use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * An account lists and turns off its own devices only; they are added by `register-device`.
 */
class PushSubscriptionControl extends Control
{
    protected string $model = PushSubscription::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => in_array($method, ['view', 'delete'], true)
                    && $user->hasVerifiedEmail())
                ->should(fn (Model $user, Model $device): bool => $device->subscribable_type === (new ReminderSetting)->getMorphClass()
                    && ReminderSetting::query()->whereKey($device->subscribable_id)->where('user_id', $user->getKey())->exists())
                ->query(fn (Builder $query, Model $user): Builder => $query
                    ->where('push_subscriptions.subscribable_type', (new ReminderSetting)->getMorphClass())
                    ->whereIn('push_subscriptions.subscribable_id', ReminderSetting::query()->select('id')->where('user_id', $user->getKey()))),
        ];
    }
}
