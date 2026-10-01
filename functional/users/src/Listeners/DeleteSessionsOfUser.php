<?php

namespace Functional\Users\Listeners;

use Functional\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * An erased account leaves no session and no password reset token behind (feature 004,
 * FR-017). HasRoles detaches its roles and permissions by itself.
 */
class DeleteSessionsOfUser
{
    public function handle(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
    }
}
