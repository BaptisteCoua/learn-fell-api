<?php

namespace Functional\Reminders\Tests\Concerns;

use Base64Url\Base64Url;
use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSetting;
use Illuminate\Support\Facades\Http;
use Minishlink\WebPush\VAPID;

/**
 * Real keys, so that the channel encrypts the messages as it would in production, and a push
 * service answered by the HTTP fake.
 */
trait FakesWebPush
{
    protected function configureVapid(): void
    {
        $keys = VAPID::createVapidKeys();

        config([
            'webpush.vapid.public_key' => $keys['publicKey'],
            'webpush.vapid.private_key' => $keys['privateKey'],
            'webpush.vapid.subject' => 'mailto:bonjour@cinq.app',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function deviceOf(ReminderSetting $setting, array $attributes = []): PushSubscription
    {
        return PushSubscription::factory()->for($setting, 'subscribable')->create([
            'public_key' => VAPID::createVapidKeys()['publicKey'],
            'auth_token' => Base64Url::encode(random_bytes(16)),
            'content_encoding' => 'aes128gcm',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, int>  $statusByEndpoint  status answered for an endpoint, 201 otherwise
     */
    protected function fakePushService(array $statusByEndpoint = []): void
    {
        Http::fake(function ($request) use ($statusByEndpoint) {
            return Http::response('', $statusByEndpoint[(string) $request->url()] ?? 201);
        });
    }
}
