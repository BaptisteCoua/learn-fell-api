<?php

namespace Functional\Reminders\Database\Factories;

use Functional\Reminders\Models\ReminderSend;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderSend>
 */
class ReminderSendFactory extends Factory
{
    protected $model = ReminderSend::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'local_date' => now('Europe/Paris')->toDateString(),
            'cards_count' => faker()->number(1, 30),
            'subject_ids' => [],
            'channels' => ['mail'],
            'sent_at' => now(),
        ];
    }
}
