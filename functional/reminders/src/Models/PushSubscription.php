<?php

namespace Functional\Reminders\Models;

use Functional\Reminders\Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Lomkit\Access\Controls\HasControl;
use NotificationChannels\WebPush\PushSubscription as WebPushSubscription;

/**
 * A browser allowed to show the reminders of an account (FR-006), named after its browser and
 * system. Its keys only serve to encrypt the messages and never leave the API.
 */
#[UseFactory(PushSubscriptionFactory::class)]
class PushSubscription extends WebPushSubscription
{
    use HasControl, HasFactory;

    protected $fillable = [
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'device_label',
        'last_delivered_at',
    ];

    protected $hidden = ['public_key', 'auth_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_delivered_at' => 'datetime',
        ];
    }
}
