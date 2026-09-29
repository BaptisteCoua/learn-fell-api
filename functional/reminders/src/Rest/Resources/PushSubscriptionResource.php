<?php

namespace Functional\Reminders\Rest\Resources;

use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Rest\Actions\RegisterDevice;
use Functional\Reminders\Support\ReminderScheduler;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

/**
 * The devices of the signed-in account (FR-006): listed newest first, turned off one by one.
 * They are added by the `register-device` action only, and their keys are never returned.
 */
class PushSubscriptionResource extends Resource
{
    public static $model = PushSubscription::class;

    public int $defaultLimit = 50;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'endpoint', 'device_label', 'last_delivered_at', 'created_at'];
    }

    /**
     * @return list<Action>
     */
    public function actions(RestRequest $request): array
    {
        return [RegisterDevice::make()->standalone()];
    }

    /**
     * The last device turned off may be the last channel.
     */
    public function destroyed(DestroyRequest $request, Model $model): void
    {
        $setting = ReminderSetting::query()->find($model->subscribable_id);

        if ($setting !== null) {
            app(ReminderScheduler::class)->refresh($setting);
        }
    }

    /**
     * @return list<int>
     */
    public function limits(RestRequest $request): array
    {
        return [10, 25, 50];
    }

    /**
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['created_at' => 'desc', 'id' => 'desc'];
    }

    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }
}
