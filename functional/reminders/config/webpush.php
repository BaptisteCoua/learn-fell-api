<?php

use Functional\Reminders\Models\PushSubscription;

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // The layer's model adds the device name and the last delivery (data-model.md).
    'model' => PushSubscription::class,

    'table_name' => 'push_subscriptions',

    // The application's default connection, rather than the package's MySQL fallback.
    'database_connection' => null,
];
