<?php

namespace Functional\Reminders\Rest\Actions;

use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Support\ReminderScheduler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Turns the reminders on for the current browser (FR-006). A browser shows the reminders of
 * one account only: registered again from another account, it moves to that account
 * (research R11), as `updatePushSubscription()` of the package does.
 */
class RegisterDevice extends Action
{
    private const FIELDS = ['endpoint', 'public_key', 'auth_token', 'content_encoding', 'device_label'];

    public function uriKey(): string
    {
        return 'register-device';
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, PushSubscription>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        $device = $this->validDevice($fields);
        $setting = ReminderSetting::query()->where('user_id', Auth::id())->firstOrFail();

        DB::transaction(function () use ($setting, $device): void {
            $known = PushSubscription::findByEndpoint($device['endpoint']);

            if ($known !== null && $setting->ownsPushSubscription($known)) {
                $known->update($device);

                return;
            }

            $known?->delete();
            $setting->pushSubscriptions()->create($device);
        });

        $setting->noteChannelChange();
        app(ReminderScheduler::class)->refresh($setting);
    }

    /**
     * Checked here rather than by the action's rules, so that the web app gets one business code.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    private function validDevice(array $fields): array
    {
        $validator = Validator::make($fields, [
            'endpoint' => ['required', 'string', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
            'public_key' => ['required', 'string', 'max:255'],
            'auth_token' => ['required', 'string', 'max:255'],
            'content_encoding' => ['required', Rule::in(['aes128gcm', 'aesgcm'])],
            'device_label' => ['required', 'string', 'max:60'],
        ]);

        if ($validator->fails()) {
            throw new BusinessRuleException('invalid_push_subscription');
        }

        return $validator->validated();
    }

    /**
     * @return array<string, list<string>>
     */
    public function fields(RestRequest $request): array
    {
        return array_fill_keys(self::FIELDS, ['nullable']);
    }
}
