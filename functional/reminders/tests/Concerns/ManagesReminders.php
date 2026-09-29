<?php

namespace Functional\Reminders\Tests\Concerns;

use Functional\Reminders\Models\ReminderSetting;
use Functional\Users\Models\User;
use Illuminate\Testing\TestResponse;

trait ManagesReminders
{
    protected function settingOf(User $user): ReminderSetting
    {
        return ReminderSetting::query()->where('user_id', $user->id)->sole();
    }

    protected function searchSettings(User $user): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/reminder-settings/search', ['search' => []]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function updateSettings(User $user, int $settingId, array $attributes): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/reminder-settings/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $settingId, 'attributes' => $attributes]],
        ]);
    }

    protected function dismissProposal(User $user): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/reminder-settings/actions/dismiss-proposal', ['fields' => []]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function registerDevice(User $user, array $overrides = []): TestResponse
    {
        $fields = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'public_key' => 'BAKmwxu4AcTsxCl4mYmBnEmpdNTbAbnzl3kGy3k2yBc3jgvjwb7DOHzmDfxq6iGNAKoB2BdTfoeRyC8HmOUN8Tg',
            'auth_token' => 'Y7i7kUd9LJgx4ynN8QLqBQ',
            'content_encoding' => 'aes128gcm',
            'device_label' => 'Chrome sur Android',
            ...$overrides,
        ];

        return $this->actingAs($user)->postJson('/api/push-subscriptions/actions/register-device', [
            'fields' => collect($fields)->map(fn (mixed $value, string $name): array => ['name' => $name, 'value' => $value])->values()->all(),
        ]);
    }

    protected function searchDevices(User $user): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/push-subscriptions/search', ['search' => []]);
    }

    protected function deleteDevice(User $user, int $deviceId): TestResponse
    {
        return $this->actingAs($user)->deleteJson('/api/push-subscriptions', ['resources' => [$deviceId]]);
    }
}
