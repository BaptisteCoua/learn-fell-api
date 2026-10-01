<?php

namespace Functional\Users\Events;

use Functional\Users\Models\User;

/**
 * An account asked to be deleted (feature 004): each layer hides what it must, inside the
 * request's transaction, so that a listener refusing the request undoes it entirely.
 */
class AccountDeletionRequested
{
    public function __construct(public readonly User $user) {}
}
