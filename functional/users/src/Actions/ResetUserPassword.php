<?php

namespace Functional\Users\Actions;

use Functional\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Sets the new password, typed twice, and closes every open session of the account
 * (FR-005): whoever knew the old password is logged out everywhere.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ])->validate();

        $user->forceFill(['password' => $input['password']])->save();

        DB::table('sessions')->where('user_id', $user->getKey())->delete();
    }
}
