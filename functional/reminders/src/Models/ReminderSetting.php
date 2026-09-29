<?php

namespace Functional\Reminders\Models;

use Functional\Reminders\Database\Factories\ReminderSettingFactory;
use Functional\Reminders\Enums\EmailDisabledReason;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Lomkit\Access\Controls\HasControl;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * The reminder settings of one account, off until the account turns a channel on (FR-001).
 * It receives the reminders itself, so the users layer knows nothing of them (research R3).
 * The push channel has no flag: it is on while the account has at least one device (FR-006).
 */
#[Fillable([
    'user_id', 'email_enabled', 'send_time', 'activated_at', 'proposal_seen_at', 'email_disabled_reason',
    'email_bounce_count', 'unsubscribe_version', 'next_reminder_at',
])]
#[UseFactory(ReminderSettingFactory::class)]
class ReminderSetting extends Model
{
    use HasControl, HasFactory, HasPushSubscriptions, Notifiable;

    public const DEFAULT_SEND_TIME = '19:00';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function routeNotificationForMail(): string
    {
        return $this->user->email;
    }

    public function hasActiveChannel(): bool
    {
        return $this->email_enabled || $this->pushSubscriptions()->exists();
    }

    /**
     * Stored as a time of day, exchanged as "HH:MM".
     *
     * @return Attribute<string, string>
     */
    protected function sendTime(): Attribute
    {
        return Attribute::make(
            get: fn (string $time): string => substr($time, 0, 5),
            set: fn (string $time): string => substr($time, 0, 5),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'activated_at' => 'datetime',
            'proposal_seen_at' => 'datetime',
            'email_disabled_reason' => EmailDisabledReason::class,
            'email_bounce_count' => 'integer',
            'unsubscribe_version' => 'integer',
            'next_reminder_at' => 'datetime',
        ];
    }
}
