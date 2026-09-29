<?php

namespace Functional\Reminders\Database\Factories;

use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscribable_type' => (new ReminderSetting)->getMorphClass(),
            'subscribable_id' => ReminderSetting::factory(),
            'endpoint' => 'https://push.example.test/'.faker()->unique()->uuid(),
            'public_key' => 'BAKmwxu4AcTsxCl4mYmBnEmpdNTbAbnzl3kGy3k2yBc3jgvjwb7DOHzmDfxq6iGNAKoB2BdTfoeRyC8HmOUN8Tg',
            'auth_token' => 'Y7i7kUd9LJgx4ynN8QLqBQ',
            'content_encoding' => 'aes128gcm',
            'device_label' => faker()->randomElement(['Chrome sur Android', 'Firefox sur Windows', 'Safari sur iPhone']),
        ];
    }
}
