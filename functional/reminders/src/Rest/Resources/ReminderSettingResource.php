<?php

namespace Functional\Reminders\Rest\Resources;

use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Rest\Actions\DismissProposal;
use Functional\Reminders\Support\ReminderScheduler;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * The reminder settings of the signed-in account (FR-003): its email channel and its time.
 * The row is created with the account, so it is only read and updated here.
 */
class ReminderSettingResource extends Resource
{
    public static $model = ReminderSetting::class;

    public int $defaultLimit = 10;

    /**
     * From 6 h to 23 h 30, on the hour or the half hour (spec, Assumptions).
     */
    private const SEND_TIME_PATTERN = '/^(0[6-9]|1\d|2[0-3]):(00|30)$/';

    private const WRITABLE_FIELDS = ['email_enabled', 'send_time'];

    private const READ_ONLY_FIELDS = [
        'id', 'activated_at', 'proposal_seen_at', 'email_disabled_reason', 'next_reminder_at', 'devices_count',
        'email_bounce_count', 'unsubscribe_version', 'user_id',
    ];

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id', 'email_enabled', 'send_time', 'activated_at', 'proposal_seen_at', 'email_disabled_reason',
            'next_reminder_at', 'devices_count',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(RestRequest $request): array
    {
        return [
            'email_enabled' => ['boolean'],
            'send_time' => ['string'],
            ...array_fill_keys(self::READ_ONLY_FIELDS, ['prohibited']),
        ];
    }

    /**
     * @return list<Action>
     */
    public function actions(RestRequest $request): array
    {
        return [DismissProposal::make()->standalone()];
    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        $attributes = array_intersect_key($requestBody['attributes'] ?? [], array_flip(self::WRITABLE_FIELDS));

        if (array_key_exists('send_time', $attributes) && preg_match(self::SEND_TIME_PATTERN, $attributes['send_time']) !== 1) {
            throw new BusinessRuleException('invalid_send_time');
        }

        // A link sent before the email was turned back on no longer turns it off (research R7).
        if (($attributes['email_enabled'] ?? false) === true && ! $model->email_enabled) {
            $model->email_disabled_reason = null;
            $model->email_bounce_count = 0;
            $model->unsubscribe_version++;
        }
    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    public function mutated(MutateRequest $request, array $requestBody, Model $model): void
    {
        $model->noteChannelChange();
        app(ReminderScheduler::class)->refresh($model);
    }

    /**
     * @return list<int>
     */
    public function limits(RestRequest $request): array
    {
        return [1, 10];
    }

    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }

    /**
     * The count goes on the final query: lomkit runs searchQuery() inside a where() group,
     * where selected columns would be dropped.
     */
    public function paginate($query, RestRequest $request)
    {
        $query->withCount('pushSubscriptions as devices_count');

        return parent::paginate($query, $request);
    }
}
