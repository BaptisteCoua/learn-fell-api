<?php

namespace Functional\Reminders\Support;

use Functional\Reminders\Models\ReminderSetting;
use Illuminate\Support\Facades\URL;

/**
 * The one-click unsubscribe links of a reminder email (research R7): signed, without expiry,
 * for the account and the number of times its email was turned back on. Turning the email
 * back on outdates the links sent before, found in an old forwarded email for instance.
 *
 * The signature is relative, as for the address confirmation of 001, so that the page of the
 * web app and the mailbox reach the API with the same one.
 */
class UnsubscribeLink
{
    public const ROUTE = 'reminders.unsubscribe';

    /**
     * In the body of the email: the web page, which calls the API and confirms.
     */
    public function forWebPage(ReminderSetting $setting): string
    {
        parse_str((string) parse_url($this->signedPath($setting), PHP_URL_QUERY), $signedQuery);

        return config('app.frontend_url').'/rappels/desinscription?'.http_build_query([
            'id' => $setting->user_id,
            ...$signedQuery,
        ]);
    }

    /**
     * In the `List-Unsubscribe` header: the API itself, called by the mailbox (RFC 8058).
     */
    public function forMailbox(ReminderSetting $setting): string
    {
        return rtrim((string) config('app.url'), '/').$this->signedPath($setting);
    }

    private function signedPath(ReminderSetting $setting): string
    {
        return URL::signedRoute(
            self::ROUTE,
            ['id' => $setting->user_id, 'v' => $setting->unsubscribe_version],
            absolute: false,
        );
    }
}
