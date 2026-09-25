<?php

namespace Functional\Users\Policies;

use Illuminate\Database\Eloquent\Model;

/**
 * Accounts are only ever exposed through PublicUserResource (id and display name), as the
 * author of a subject or a report. Reading those two fields is open to everyone.
 */
class UserPolicy
{
    public function viewAny(?Model $user): bool
    {
        return true;
    }

    public function view(?Model $user, Model $account): bool
    {
        return true;
    }
}
