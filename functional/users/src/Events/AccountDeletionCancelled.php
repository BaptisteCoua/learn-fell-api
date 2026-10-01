<?php

namespace Functional\Users\Events;

use Functional\Users\Models\User;

/**
 * An account logged in during its grace period (feature 004): each layer restores what it hid.
 */
class AccountDeletionCancelled
{
    public function __construct(public readonly User $user) {}
}
