<?php

namespace Functional\Reminders\Enums;

/**
 * Why the reminder emails of an account stopped without the account turning them off itself.
 */
enum EmailDisabledReason: string
{
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
}
